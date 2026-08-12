<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVideoPlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_play_increments_counter_for_products_with_video(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => 'Test Store',
            'store_name' => 'Test Store',
            'slug' => 'test-store',
            'status' => SellerStatus::Approved,
            'setup_completed_at' => now(),
            'published_at' => now(),
            'published_settings' => [],
            'draft_settings' => [],
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Toyota CRV',
            'slug' => 'toyota-crv',
            'price' => 20000,
            'quantity' => 1,
            'status' => ProductStatus::Approved,
            'video_path' => 'products/videos/demo.mp4',
            'video_duration' => 21,
        ]);

        $this->postJson("/api/v1/products/{$product->slug}/video-play")
            ->assertOk()
            ->assertJson(['video_plays' => 1]);

        $product->refresh();
        $this->assertSame(1, (int) $product->video_plays);
    }

    public function test_video_play_rejects_products_without_video(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => 'Test Store',
            'store_name' => 'Test Store',
            'slug' => 'test-store-2',
            'status' => SellerStatus::Approved,
            'setup_completed_at' => now(),
            'published_at' => now(),
            'published_settings' => [],
            'draft_settings' => [],
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'No Video Item',
            'slug' => 'no-video-item',
            'price' => 100,
            'quantity' => 1,
            'status' => ProductStatus::Approved,
        ]);

        $this->postJson("/api/v1/products/{$product->slug}/video-play")
            ->assertStatus(422);
    }
}
