<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Product gallery videos must play on desktop Chrome/Edge/Firefox.
 * Phone uploads are often HEVC (hvc1); Safari plays them, PC browsers show a black frame.
 *
 * Transcode is best-effort: never throw out of the HTTP request (shared hosts often
 * kill long ffmpeg runs → generic "Server Error" for some phone clips only).
 */
class ProductVideoService
{
    /**
     * Store an uploaded product video and re-encode to H.264 when needed/possible.
     */
    public static function storeUploaded(UploadedFile $file, string $directory = 'products/videos'): string
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(90);
        }

        $path = $file->store($directory, 'public');

        try {
            return static::ensureWebCompatible($path)['path'] ?? $path;
        } catch (Throwable $e) {
            Log::warning('Product video post-process threw; keeping original upload.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return $path;
        }
    }

    /**
     * Re-encode an existing public-disk video if it will black-screen on PC browsers.
     *
     * @return array{path: string|null, ok: bool, reason: ?string}
     */
    public static function ensureWebCompatible(string $relativePath): array
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            return ['path' => null, 'ok' => false, 'reason' => 'Video file not found on disk.'];
        }

        $absolute = $disk->path($relativePath);
        if (! static::needsWebCompatTranscode($absolute)) {
            return ['path' => $relativePath, 'ok' => true, 'reason' => null];
        }

        $ffmpeg = static::ffmpegBinary();
        if ($ffmpeg === null) {
            Log::warning('Product video needs H.264 transcode but ffmpeg is not available.', [
                'path' => $relativePath,
            ]);

            return [
                'path' => $relativePath,
                'ok' => false,
                'reason' => 'ffmpeg is not installed (or not in PATH). Install ffmpeg, or set FFMPEG_PATH in .env',
            ];
        }

        $dir = trim(dirname($relativePath), '.');
        $outRelative = ($dir === '' ? '' : $dir.'/').Str::uuid()->toString().'-h264.mp4';
        $outAbsolute = $disk->path($outRelative);

        try {
            $result = Process::timeout(45)->run([
                $ffmpeg,
                '-y',
                '-i', $absolute,
                '-map', '0:v:0',
                '-map', '0:a:0?',
                '-c:v', 'libx264',
                '-preset', 'ultrafast',
                '-crf', '28',
                '-pix_fmt', 'yuv420p',
                '-profile:v', 'main',
                '-level', '4.0',
                '-movflags', '+faststart',
                '-c:a', 'aac',
                '-b:a', '96k',
                '-ac', '2',
                '-shortest',
                $outAbsolute,
            ]);
        } catch (ProcessTimedOutException $e) {
            Log::warning('Product video H.264 transcode timed out; keeping original.', [
                'path' => $relativePath,
            ]);
            @unlink($outAbsolute);

            return [
                'path' => $relativePath,
                'ok' => false,
                'reason' => 'ffmpeg timed out — original file kept.',
            ];
        } catch (Throwable $e) {
            Log::warning('Product video H.264 transcode threw; keeping original.', [
                'path' => $relativePath,
                'error' => $e->getMessage(),
            ]);
            @unlink($outAbsolute);

            return [
                'path' => $relativePath,
                'ok' => false,
                'reason' => 'ffmpeg error — original file kept.',
            ];
        }

        if (! $result->successful() || ! is_file($outAbsolute) || filesize($outAbsolute) < 1000) {
            $stderr = trim(Str::limit($result->errorOutput(), 500));
            Log::warning('Product video H.264 transcode failed.', [
                'path' => $relativePath,
                'stderr' => $stderr,
            ]);
            @unlink($outAbsolute);

            // Retry without audio — some phone clips have broken / missing audio tracks.
            return static::transcodeVideoOnly($ffmpeg, $absolute, $relativePath, $outRelative, $outAbsolute, $disk)
                ?? [
                    'path' => $relativePath,
                    'ok' => false,
                    'reason' => $stderr !== '' ? 'ffmpeg failed: '.$stderr : 'ffmpeg failed to write H.264 output.',
                ];
        }

        $disk->delete($relativePath);

        return ['path' => $outRelative, 'ok' => true, 'reason' => null];
    }

    /**
     * @param  \Illuminate\Contracts\Filesystem\Filesystem  $disk
     * @return array{path: string, ok: bool, reason: null}|null
     */
    private static function transcodeVideoOnly(
        string $ffmpeg,
        string $absolute,
        string $relativePath,
        string $outRelative,
        string $outAbsolute,
        $disk,
    ): ?array {
        try {
            $result = Process::timeout(40)->run([
                $ffmpeg,
                '-y',
                '-i', $absolute,
                '-map', '0:v:0',
                '-an',
                '-c:v', 'libx264',
                '-preset', 'ultrafast',
                '-crf', '28',
                '-pix_fmt', 'yuv420p',
                '-movflags', '+faststart',
                $outAbsolute,
            ]);
        } catch (Throwable $e) {
            @unlink($outAbsolute);

            return null;
        }

        if (! $result->successful() || ! is_file($outAbsolute) || filesize($outAbsolute) < 1000) {
            @unlink($outAbsolute);

            return null;
        }

        $disk->delete($relativePath);

        return ['path' => $outRelative, 'ok' => true, 'reason' => null];
    }

    public static function needsWebCompatTranscode(string $absolutePath): bool
    {
        if (! is_file($absolutePath) || filesize($absolutePath) < 32) {
            return false;
        }

        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $hevc = false;
        $chunkSize = 1024 * 1024;
        // Only scan the first ~8MB — enough for codec boxes, avoids huge reads.
        $scanned = 0;
        $maxScan = 8 * 1024 * 1024;
        while (! feof($handle) && $scanned < $maxScan) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $scanned += strlen($chunk);
            if (str_contains($chunk, 'hvc1') || str_contains($chunk, 'hev1') || str_contains($chunk, 'hvcC')) {
                $hevc = true;
                break;
            }
            if (str_contains($chunk, 'vp09') || str_contains($chunk, 'av01')) {
                fclose($handle);

                return true;
            }
        }
        fclose($handle);

        return $hevc;
    }

    public static function ffmpegBinary(): ?string
    {
        $configured = trim((string) config('services.ffmpeg_path', env('FFMPEG_PATH', '')));
        if ($configured !== '' && is_executable($configured)) {
            return $configured;
        }

        $candidates = [
            base_path('bin/ffmpeg'),
            storage_path('bin/ffmpeg'),
            getenv('HOME') ? rtrim((string) getenv('HOME'), '/').'/bin/ffmpeg' : null,
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/opt/homebrew/bin/ffmpeg',
        ];

        foreach ($candidates as $bin) {
            if (is_string($bin) && $bin !== '' && is_executable($bin)) {
                return $bin;
            }
        }

        $result = Process::run(['which', 'ffmpeg']);
        $path = trim($result->output());
        if ($result->successful() && $path !== '' && is_executable($path)) {
            return $path;
        }

        return null;
    }
}
