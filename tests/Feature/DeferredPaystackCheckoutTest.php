<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Support\PendingCheckoutDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeferredPaystackCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_momo_checkout_does_not_create_order_until_payment_succeeds(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'email' => 'buyer@example.com']);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Secure Shop',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'accept_direct_payments' => false,
            'accept_marketplace_payments' => true,
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Electric bike',
            'slug' => 'electric-bike-secure',
            'price' => 500,
            'quantity' => 2,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
        ]);

        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $address = BuyerAddress::create([
            'user_id' => $buyer->id,
            'first_name' => 'Kofi',
            'last_name' => 'Amoah',
            'phone' => '0538790083',
            'address_line' => 'Sefwi Bekwai',
            'region' => 'Western North',
            'city' => 'Sefwi Bekwai',
            'is_default' => true,
        ]);

        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/checkout', [
            'address_id' => $address->id,
            'payment_method' => 'momo',
            'seller_payments' => [
                (string) $seller->id => ['channel' => 'marketplace'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('next', 'paystack')
            ->assertJsonPath('amount', 500);

        $this->assertSame(0, Order::count());
        $this->assertNotNull(PendingCheckoutDraft::getForUser($buyer));
        $this->assertSame(1, CartItem::where('user_id', $buyer->id)->count());
    }
}
