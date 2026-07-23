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

class WishlistToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_wishlist_toggle_updates_like_count_up_and_down(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Like Shop',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Liked Product',
            'slug' => 'liked-product',
            'price' => 100,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
            'wishlist_adds' => 0,
        ]);

        $this->actingAs($buyer)
            ->from(route('products.show', $product->slug))
            ->post(route('wishlist.toggle'), ['product_id' => $product->id])
            ->assertRedirect();

        $this->assertSame(1, (int) $product->fresh()->wishlist_adds);

        $this->actingAs($buyer)
            ->from(route('products.show', $product->slug))
            ->post(route('wishlist.toggle'), ['product_id' => $product->id])
            ->assertRedirect();

        $this->assertSame(0, (int) $product->fresh()->wishlist_adds);

        $this->actingAs($buyer)
            ->from(route('products.show', $product->slug))
            ->post(route('wishlist.toggle'), ['product_id' => $product->id])
            ->assertRedirect();

        $this->assertSame(1, (int) $product->fresh()->wishlist_adds);
    }
}
