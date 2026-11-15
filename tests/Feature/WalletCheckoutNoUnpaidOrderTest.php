<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\PaymentPinService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WalletCheckoutNoUnpaidOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_insufficient_wallet_does_not_create_an_order(): void
    {
        [$buyer, $seller, $address] = $this->buyerWithCart(productPrice: 530, walletBalance: 109.31);
        PaymentPinService::set($buyer, '2468');
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/checkout', [
            'address_id' => $address->id,
            'payment_method' => 'wallet',
            'payment_pin' => '2468',
            'seller_payments' => [
                (string) $seller->id => ['channel' => 'marketplace'],
            ],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);

        $this->assertSame(0, Order::count());
        $this->assertSame(0, Checkout::count());
        $this->assertSame(1, CartItem::where('user_id', $buyer->id)->count());
        $this->assertEqualsWithDelta(
            109.31,
            (float) WalletService::ensure($buyer)->available_balance,
            0.01,
        );
    }

    public function test_sufficient_wallet_creates_a_paid_order(): void
    {
        [$buyer, $seller, $address] = $this->buyerWithCart(productPrice: 100, walletBalance: 250);
        PaymentPinService::set($buyer, '2468');
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/checkout', [
            'address_id' => $address->id,
            'payment_method' => 'wallet',
            'payment_pin' => '2468',
            'seller_payments' => [
                (string) $seller->id => ['channel' => 'marketplace'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('next', 'orders');

        $this->assertSame(1, Order::count());
        $this->assertSame(PaymentStatus::Paid, Order::query()->first()->payment_status);
        $this->assertSame(0, CartItem::where('user_id', $buyer->id)->count());
    }

    /**
     * @return array{0: User, 1: User, 2: BuyerAddress}
     */
    private function buyerWithCart(float $productPrice, float $walletBalance): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
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
            'name' => 'Phone case',
            'slug' => 'phone-case-wallet-'.uniqid(),
            'price' => $productPrice,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
        ]);

        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        WalletService::ensure($buyer)->update(['available_balance' => $walletBalance]);

        $address = BuyerAddress::create([
            'user_id' => $buyer->id,
            'first_name' => 'Robert',
            'last_name' => 'Asare',
            'phone' => '0539798093',
            'address_line' => 'Sefwi Bekwai',
            'region' => 'Western North',
            'city' => 'Sefwi Bekwai',
            'is_default' => true,
        ]);

        return [$buyer, $seller, $address];
    }
}
