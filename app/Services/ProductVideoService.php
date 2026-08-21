<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Product gallery videos must play on desktop Chrome/Edge/Firefox.
 * Phone uploads are often HEVC (hvc1); Safari plays them, PC browsers show a black frame.
 */
class ProductVideoService
{
    /**
     * Store an uploaded product video and re-encode to H.264 when needed/possible.
     */
    public static function storeUploaded(UploadedFile $file, string $directory = 'products/videos'): string
    {
        $path = $file->store($directory, 'public');

        return static::ensureWebCompatible($path)['path'] ?? $path;
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

        $result = Process::timeout(300)->run([
            $ffmpeg,
            '-y',
            '-i', $absolute,
            '-c:v', 'libx264',
            '-pix_fmt', 'yuv420p',
            '-profile:v', 'main',
            '-level', '4.0',
            '-movflags', '+faststart',
            '-c:a', 'aac',
            '-b:a', '128k',
            '-ac', '2',
            $outAbsolute,
        ]);

        if (! $result->successful() || ! is_file($outAbsolute) || filesize($outAbsolute) < 1000) {
            $stderr = trim(Str::limit($result->errorOutput(), 500));
            Log::warning('Product video H.264 transcode failed.', [
                'path' => $relativePath,
                'stderr' => $stderr,
            ]);
            @unlink($outAbsolute);

            return [
                'path' => $relativePath,
                'ok' => false,
                'reason' => $stderr !== '' ? 'ffmpeg failed: '.$stderr : 'ffmpeg failed to write H.264 output.',
            ];
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
        while (! feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }
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
