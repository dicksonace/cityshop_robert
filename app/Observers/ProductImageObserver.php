<?php

namespace App\Observers;

use App\Models\ProductImage;
use App\Services\ImageSearchService;

class ProductImageObserver
{
    public function __construct(private ImageSearchService $imageSearch) {}

    public function created(ProductImage $image): void
    {
        $this->imageSearch->indexImage($image);
    }

    public function updated(ProductImage $image): void
    {
        if ($image->wasChanged('path')) {
            $this->imageSearch->indexImage($image);
        }
    }
}