<?php

namespace App\Observers;

use App\Models\ProductImage;
use App\Services\ImageSearchService;
use Illuminate\Support\Facades\Log;

class ProductImageObserver
{
    public function __construct(private ImageSearchService $imageSearch) {}

    public function created(ProductImage $image): void
    {
        $this->queueIndex($image->id);
    }

    public function updated(ProductImage $image): void
    {
        if ($image->wasChanged('path')) {
            $this->queueIndex($image->id);
        }
    }

    private function queueIndex(int $imageId): void
    {
        dispatch(function () use ($imageId) {
            $image = ProductImage::find($imageId);

            if (! $image) {
                return;
            }

            try {
                $this->imageSearch->indexImage($image);
            } catch (\Throwable $e) {
                Log::warning('Product image color index failed', [
                    'product_image_id' => $imageId,
                    'message' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }
}