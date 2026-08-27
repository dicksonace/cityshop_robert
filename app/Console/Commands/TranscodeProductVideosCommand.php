<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductVideoService;
use Illuminate\Console\Command;

class TranscodeProductVideosCommand extends Command
{
    protected $signature = 'products:transcode-videos
                            {--dry-run : Only list videos that need conversion}
                            {--force : Re-encode every product video to H.264 even if detection says OK}';

    protected $description = 'Re-encode product videos that Chrome cannot play (HEVC/AV1/etc.) to H.264';

    public function handle(): int
    {
        $ffmpeg = ProductVideoService::ffmpegBinary();
        if ($ffmpeg) {
            $this->info('Using ffmpeg: '.$ffmpeg);
        } else {
            $this->warn('ffmpeg not found. Install it, or put a static binary at ~/bin/ffmpeg and set FFMPEG_PATH.');
            if (! $this->option('dry-run')) {
                $this->error('Cannot convert without ffmpeg.');

                return self::FAILURE;
            }
        }

        $ffprobe = ProductVideoService::ffprobeBinary();
        $this->info($ffprobe ? 'Using ffprobe: '.$ffprobe : 'ffprobe not found — falling back to file-marker detection.');

        $force = (bool) $this->option('force');
        if ($force) {
            $this->warn('Force mode: every product video will be re-encoded to H.264.');
        }

        $query = Product::query()->whereNotNull('video_path')->where('video_path', '!=', '');
        $total = $query->count();
        $this->info("Scanning {$total} product video(s)…");

        $converted = 0;
        $skipped = 0;
        $failed = 0;

        $query->orderBy('id')->each(function (Product $product) use (&$converted, &$skipped, &$failed, $force) {
            $path = (string) $product->video_path;
            $absolute = storage_path('app/public/'.$path);

            if (! is_file($absolute)) {
                $failed++;
                $this->error("Missing file: #{$product->id} {$path}");

                return;
            }

            $codec = ProductVideoService::describeVideoCodec($absolute);
            $needs = $force || ProductVideoService::needsWebCompatTranscode($absolute);

            if (! $needs) {
                $skipped++;
                $this->line("OK (skip): #{$product->id} codec={$codec} {$path}");

                return;
            }

            $this->line(($force ? 'FORCE' : 'CONVERT').": #{$product->id} {$product->name} codec={$codec} ({$path})");

            if ($this->option('dry-run')) {
                $converted++;

                return;
            }

            $result = ProductVideoService::ensureWebCompatible($path, $force);
            if (! ($result['ok'] ?? false)) {
                $failed++;
                $this->error('  failed to convert #'.$product->id.': '.($result['reason'] ?? 'unknown error'));

                return;
            }

            $newPath = $result['path'] ?? $path;
            if ($newPath !== $path) {
                $product->update(['video_path' => $newPath]);
                $converted++;
                $this->info("  → {$newPath}");
            } else {
                // force encode can rewrite same relative path only if detection skipped — treat as skipped
                $skipped++;
                $this->line('  → unchanged (already compatible)');
            }
        });

        $this->newLine();
        $this->info("Done. converted={$converted} skipped={$skipped} failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
