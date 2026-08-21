<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ProductVideoService;
use Illuminate\Console\Command;

class TranscodeProductVideosCommand extends Command
{
    protected $signature = 'products:transcode-videos {--dry-run : Only list videos that need conversion}';

    protected $description = 'Re-encode product videos that are HEVC/AV1 so they play on PC browsers';

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

        $query = Product::query()->whereNotNull('video_path')->where('video_path', '!=', '');
        $total = $query->count();
        $this->info("Scanning {$total} product video(s)…");

        $converted = 0;
        $skipped = 0;
        $failed = 0;

        $query->orderBy('id')->each(function (Product $product) use (&$converted, &$skipped, &$failed) {
            $path = (string) $product->video_path;
            $absolute = storage_path('app/public/'.$path);

            if (! is_file($absolute)) {
                $failed++;
                $this->error("Missing file: #{$product->id} {$path}");

                return;
            }

            if (! ProductVideoService::needsWebCompatTranscode($absolute)) {
                $skipped++;

                return;
            }

            $this->line("HEVC/incompatible: #{$product->id} {$product->name} ({$path})");

            if ($this->option('dry-run')) {
                $converted++;

                return;
            }

            $result = ProductVideoService::ensureWebCompatible($path);
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
                $skipped++;
            }
        });

        $this->newLine();
        $this->info("Done. converted={$converted} skipped={$skipped} failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
