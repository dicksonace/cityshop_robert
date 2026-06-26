<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Services\ImageSearchService;
use Illuminate\Console\Command;

class IndexProductImageColors extends Command
{
    protected $signature = 'products:index-image-colors';

    protected $description = 'Build color signatures for visual image search';

    public function handle(ImageSearchService $imageSearch): int
    {
        $this->info('Indexing product image colors...');

        $bar = $this->output->createProgressBar(ProductImage::count());
        $bar->start();

        ProductImage::query()->orderBy('id')->chunkById(100, function ($images) use ($imageSearch, $bar) {
            foreach ($images as $image) {
                $imageSearch->indexImage($image);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }
}
