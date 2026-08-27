<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Product gallery videos must play on desktop Chrome/Edge/Firefox.
 * Phone uploads are often HEVC (hvc1); Safari/app play them, PC Chrome shows a black frame.
 *
 * Upload only stores the file (fast). Conversion runs after the HTTP response so
 * shared hosts do not kill the request mid-ffmpeg.
 */
class ProductVideoService
{
    /**
     * Store an uploaded product video. Conversion is scheduled separately.
     */
    public static function storeUploaded(UploadedFile $file, string $directory = 'products/videos'): string
    {
        return $file->store($directory, 'public');
    }

    /**
     * After the response is sent, re-encode the product video for PC browsers when needed.
     */
    public static function scheduleProductWebCompat(Product $product): void
    {
        $path = (string) ($product->video_path ?? '');
        if ($path === '') {
            return;
        }

        $productId = (int) $product->id;

        app()->terminating(function () use ($productId, $path) {
            if (function_exists('ignore_user_abort')) {
                ignore_user_abort(true);
            }
            if (function_exists('set_time_limit')) {
                @set_time_limit(240);
            }

            try {
                $product = Product::query()->find($productId);
                if (! $product || (string) $product->video_path !== $path) {
                    return;
                }

                $result = static::ensureWebCompatible($path);
                $newPath = $result['path'] ?? $path;
                if (($result['ok'] ?? false) && is_string($newPath) && $newPath !== '' && $newPath !== $path) {
                    $product->update(['video_path' => $newPath]);
                } elseif (! ($result['ok'] ?? false)) {
                    Log::warning('Deferred product video transcode did not finish.', [
                        'product_id' => $productId,
                        'path' => $path,
                        'reason' => $result['reason'] ?? null,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('Deferred product video transcode threw.', [
                    'product_id' => $productId,
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Re-encode an existing public-disk video if it will black-screen on PC browsers.
     *
     * @return array{path: string|null, ok: bool, reason: ?string}
     */
    public static function ensureWebCompatible(string $relativePath, bool $force = false): array
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            return ['path' => null, 'ok' => false, 'reason' => 'Video file not found on disk.'];
        }

        $absolute = $disk->path($relativePath);
        if (! $force && ! static::needsWebCompatTranscode($absolute)) {
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
            $result = Process::timeout(180)->run([
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
            $result = Process::timeout(180)->run([
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

    /**
     * True when Chrome/Edge/Firefox are likely to fail (HEVC/AV1/VP9/unknown).
     * Prefers ffprobe; falls back to MP4 box markers.
     */
    public static function needsWebCompatTranscode(string $absolutePath): bool
    {
        if (! is_file($absolutePath) || filesize($absolutePath) < 32) {
            return false;
        }

        $codec = static::probeVideoCodec($absolutePath);
        if ($codec !== null) {
            return ! static::isChromeSafeVideoCodec($codec);
        }

        return static::needsWebCompatTranscodeByMarkers($absolutePath);
    }

    /**
     * Human-readable codec label for artisan logging (e.g. "hevc", "h264", "unknown").
     */
    public static function describeVideoCodec(string $absolutePath): string
    {
        $codec = static::probeVideoCodec($absolutePath);
        if ($codec !== null && $codec !== '') {
            return $codec;
        }

        if (! is_file($absolutePath)) {
            return 'missing';
        }

        if (static::needsWebCompatTranscodeByMarkers($absolutePath)) {
            return 'incompatible-markers';
        }

        return 'unknown';
    }

    public static function isChromeSafeVideoCodec(string $codec): bool
    {
        $codec = strtolower(trim($codec));

        return in_array($codec, ['h264', 'avc', 'avc1', 'avc3', 'avcH'], true);
    }

    /**
     * @return string|null Lowercase codec_name from ffprobe, or null if probe unavailable/failed
     */
    public static function probeVideoCodec(string $absolutePath): ?string
    {
        $ffprobe = static::ffprobeBinary();
        if ($ffprobe === null || ! is_file($absolutePath)) {
            return null;
        }

        try {
            $result = Process::timeout(30)->run([
                $ffprobe,
                '-v', 'error',
                '-select_streams', 'v:0',
                '-show_entries', 'stream=codec_name',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $absolutePath,
            ]);
        } catch (Throwable $e) {
            return null;
        }

        if (! $result->successful()) {
            return null;
        }

        $codec = strtolower(trim($result->output()));
        if ($codec === '' || str_contains($codec, ' ')) {
            return null;
        }

        return $codec;
    }

    public static function needsWebCompatTranscodeByMarkers(string $absolutePath): bool
    {
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return false;
        }

        $chunkSize = 1024 * 1024;
        // Only scan the first ~8MB — enough for codec boxes, avoids huge reads.
        $scanned = 0;
        $maxScan = 8 * 1024 * 1024;
        $sawChromeSafe = false;
        $sawUnsafe = false;

        while (! feof($handle) && $scanned < $maxScan) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $scanned += strlen($chunk);

            if (
                str_contains($chunk, 'hvc1')
                || str_contains($chunk, 'hev1')
                || str_contains($chunk, 'hvcC')
                || str_contains($chunk, 'dvh1')
                || str_contains($chunk, 'dvhe')
                || str_contains($chunk, 'vp09')
                || str_contains($chunk, 'av01')
                || str_contains($chunk, 'vp08')
            ) {
                $sawUnsafe = true;
                break;
            }

            if (
                str_contains($chunk, 'avc1')
                || str_contains($chunk, 'avc3')
                || str_contains($chunk, 'avcC')
            ) {
                $sawChromeSafe = true;
            }
        }
        fclose($handle);

        if ($sawUnsafe) {
            return true;
        }

        // No H.264 marker found — treat as unsafe (common for odd phone .mov/.3gp uploads).
        return ! $sawChromeSafe;
    }

    public static function ffmpegBinary(): ?string
    {
        return static::resolveBinary('ffmpeg', [
            trim((string) config('services.ffmpeg_path', env('FFMPEG_PATH', ''))),
            base_path('bin/ffmpeg'),
            storage_path('bin/ffmpeg'),
            getenv('HOME') ? rtrim((string) getenv('HOME'), '/').'/bin/ffmpeg' : null,
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            '/opt/homebrew/bin/ffmpeg',
        ]);
    }

    public static function ffprobeBinary(): ?string
    {
        $ffmpeg = static::ffmpegBinary();
        $besideFfmpeg = [];
        if ($ffmpeg !== null) {
            $besideFfmpeg[] = dirname($ffmpeg).'/ffprobe';
        }

        return static::resolveBinary('ffprobe', array_merge($besideFfmpeg, [
            trim((string) config('services.ffprobe_path', env('FFPROBE_PATH', ''))),
            base_path('bin/ffprobe'),
            storage_path('bin/ffprobe'),
            getenv('HOME') ? rtrim((string) getenv('HOME'), '/').'/bin/ffprobe' : null,
            '/usr/bin/ffprobe',
            '/usr/local/bin/ffprobe',
            '/opt/homebrew/bin/ffprobe',
        ]));
    }

    /**
     * @param  list<string|null>  $candidates
     */
    private static function resolveBinary(string $whichName, array $candidates): ?string
    {
        foreach ($candidates as $bin) {
            if (is_string($bin) && $bin !== '' && is_executable($bin)) {
                return $bin;
            }
        }

        $result = Process::run(['which', $whichName]);
        $path = trim($result->output());
        if ($result->successful() && $path !== '' && is_executable($path)) {
            return $path;
        }

        return null;
    }
}
