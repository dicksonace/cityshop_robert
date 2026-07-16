<?php

namespace Tests\Feature;

use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerProductVideoTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSeller(): User
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Test Store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);

        StoreCustomization::create([
            'seller_profile_id' => $profile->id,
            'setup_completed_at' => now(),
            'published_at' => now(),
            'published_settings' => [],
            'draft_settings' => [],
        ]);

        return $seller->fresh();
    }

    public function test_product_can_be_published_with_video_and_paid_delivery(): void
    {
        Storage::fake('public');

        $seller = $this->approvedSeller();
        $video = UploadedFile::fake()->create('clip.mp4', 1024, 'video/mp4');
        $image = UploadedFile::fake()->image('item.jpg', 400, 400);

        $response = $this->actingAs($seller)->post(route('seller.products.store'), [
            'name' => 'Phone with video',
            'price' => 500,
            'quantity' => 2,
            'shipping_type' => 'paid',
            'free_shipping' => false,
            'delivery_fee' => 25,
            'delivery_days' => 3,
            'in_ghana' => true,
            'ships_nationwide' => true,
            'images' => [$image],
            'image_count' => 1,
            'video' => $video,
            'video_duration' => 28,
        ]);

        $response->assertRedirect(route('seller.products.index'));

        $product = Product::where('seller_id', $seller->id)->first();
        $this->assertNotNull($product);
        $this->assertFalse($product->free_shipping);
        $this->assertEquals(25.0, (float) $product->delivery_fee);
        $this->assertNotNull($product->video_path);
        $this->assertEquals(28, $product->video_duration);
        Storage::disk('public')->assertExists($product->video_path);
    }

    public function test_paid_delivery_requires_a_fee(): void
    {
        Storage::fake('public');

        $seller = $this->approvedSeller();
        $image = UploadedFile::fake()->image('item.jpg', 400, 400);

        $response = $this->actingAs($seller)->from(route('seller.products.create'))->post(route('seller.products.store'), [
            'name' => 'Missing fee product',
            'price' => 100,
            'quantity' => 1,
            'shipping_type' => 'paid',
            'free_shipping' => false,
            'delivery_fee' => '',
            'in_ghana' => true,
            'images' => [$image],
            'image_count' => 1,
        ]);

        $response->assertRedirect(route('seller.products.create'));
        $response->assertSessionHasErrors('delivery_fee');
        $this->assertDatabaseCount('products', 0);
    }
}
