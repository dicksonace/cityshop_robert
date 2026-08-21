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

        return static::ensureWebCompatible($path) ?? $path;
    }

    /**
     * Re-encode an existing public-disk video if it will black-screen on PC browsers.
     * Returns the (possibly new) relative path.
     */
    public static function ensureWebCompatible(string $relativePath): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($relativePath)) {
            return null;
        }

        $absolute = $disk->path($relativePath);
        if (! static::needsWebCompatTranscode($absolute)) {
            return $relativePath;
        }

        $ffmpeg = static::ffmpegBinary();
        if ($ffmpeg === null) {
            Log::warning('Product video needs H.264 transcode but ffmpeg is not available.', [
                'path' => $relativePath,
            ]);

            return $relativePath;
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
            Log::warning('Product video H.264 transcode failed.', [
                'path' => $relativePath,
                'stderr' => Str::limit($result->errorOutput(), 2000),
            ]);
            @unlink($outAbsolute);

            return $relativePath;
        }

        $disk->delete($relativePath);

        return $outRelative;
    }

    public static function needsWebCompatTranscode(string $absolutePath): bool
    {
        if (! is_file($absolutePath) || filesize($absolutePath) < 32) {
            return false;
        }

        // Scan container atoms — HEVC in MP4 uses hvc1/hev1; VP9/AV1 also poor on older PCs.
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

        // HEVC plays on Safari/iPhone but shows a black frame on most PC Chrome/Edge builds.
        return $hevc;
    }

    public static function ffmpegBinary(): ?string
    {
        $candidates = ['ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/opt/homebrew/bin/ffmpeg'];
        foreach ($candidates as $bin) {
            if ($bin !== 'ffmpeg' && is_executable($bin)) {
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
