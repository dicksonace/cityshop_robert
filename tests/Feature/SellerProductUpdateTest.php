<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerProductUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSellerWithProduct(): array
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Update Store',
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

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Electric bike',
            'slug' => 'electric-bike-'.uniqid(),
            'price' => 150,
            'quantity' => 1,
            'status' => ProductStatus::Approved,
            'free_shipping' => false,
            'pickup_available' => true,
            'is_negotiable' => true,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/bike.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        return [$seller, $product];
    }

    public function test_seller_can_update_product_without_uploading_new_images(): void
    {
        [$seller, $product] = $this->approvedSellerWithProduct();

        $response = $this->actingAs($seller)->put(route('seller.products.update', $product), [
            'name' => 'Electric bike updated',
            'price' => 150,
            'quantity' => 3,
            'shipping_type' => 'buyer',
            'free_shipping' => false,
            'delivery_fee' => '',
            'delivery_days' => 2,
            'weight' => '',
            'discount_price' => '',
            'wholesale_price' => '',
            'minimum_order_quantity' => 1,
            'is_negotiable' => true,
            'cash_on_delivery' => false,
            'pickup_available' => true,
            'ships_nationwide' => true,
            'in_ghana' => true,
            'condition' => 'new',
            'meta_keywords' => 'benze',
            'image_count' => 0,
            'remove_video' => false,
            'video_duration' => '',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('seller.products.index'));

        $product->refresh();
        $this->assertSame('Electric bike updated', $product->name);
        $this->assertSame(3, $product->quantity);
        $this->assertSame(2, $product->delivery_days);
        $this->assertSame('benze', $product->meta_keywords);
    }
}
