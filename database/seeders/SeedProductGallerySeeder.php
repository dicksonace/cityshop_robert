<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;

class SeedProductGallerySeeder extends Seeder
{
    public function run(): void
    {
        $gallery = [
            1 => [
                'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=600&h=600&fit=crop',
            ],
            2 => [
                'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=600&h=600&fit=crop',
            ],
            4 => [
                'https://images.unsplash.com/photo-1610945415295-d9bbf067e59c?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1598327105666-5b05001aa4bf?w=600&h=600&fit=crop',
                'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=600&h=600&fit=crop',
            ],
        ];

        foreach ($gallery as $productId => $urls) {
            if (! Product::find($productId)) {
                continue;
            }

            ProductImage::where('product_id', $productId)->delete();

            foreach ($urls as $index => $url) {
                ProductImage::create([
                    'product_id' => $productId,
                    'path' => $url,
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }
    }
}
