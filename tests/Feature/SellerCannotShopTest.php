<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerCannotShopTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSeller(): User
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Blocked Shop',
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

    public function test_seller_cannot_add_product_to_cart(): void
    {
        $seller = $this->approvedSeller();
        $otherSeller = $this->approvedSeller();

        $product = Product::create([
            'seller_id' => $otherSeller->id,
            'name' => 'Buyer Only Phone',
            'slug' => 'buyer-only-phone',
            'price' => 100,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
            'is_preorder' => false,
        ]);

        $this->actingAs($seller)
            ->post(route('cart.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('seller.dashboard'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $seller->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_seller_cannot_open_checkout(): void
    {
        $seller = $this->approvedSeller();

        $this->actingAs($seller)
            ->get(route('checkout.index'))
            ->assertRedirect(route('seller.dashboard'))
            ->assertSessionHas('error');
    }

    public function test_buyer_can_still_add_to_cart(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller();

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Normal Phone',
            'slug' => 'normal-phone',
            'price' => 80,
            'quantity' => 3,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
            'is_preorder' => false,
        ]);

        $this->actingAs($buyer)
            ->post(route('cart.store'), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
        ]);
    }
}
