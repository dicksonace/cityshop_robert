<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiOrderSellerTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_block_carries_the_store_logo_the_app_shows(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $order = $this->orderFor($buyer, shopPhoto: 'sellers/documents/shop-front.jpg');

        Sanctum::actingAs($buyer);

        $response = $this->getJson("/api/v1/orders/{$order->id}")->assertOk();

        $response->assertJsonPath('data.seller.store_name', 'City Unlock')
            ->assertJsonPath('data.seller.store_slug', 'city-unlock');

        $logo = $response->json('data.seller.store_logo');
        $this->assertIsString($logo);
        $this->assertStringStartsWith('http', $logo);
        $this->assertStringContainsString('sellers/documents/shop-front.jpg', $logo);
    }

    public function test_a_sellers_own_profile_picture_wins_over_the_shop_photo(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $order = $this->orderFor(
            $buyer,
            shopPhoto: 'sellers/documents/shop-front.jpg',
            avatar: 'avatars/city-unlock.png',
        );

        Sanctum::actingAs($buyer);

        $logo = $this->getJson("/api/v1/orders/{$order->id}")->assertOk()->json('data.seller.store_logo');
        $this->assertStringContainsString('avatars/city-unlock.png', (string) $logo);
    }

    public function test_absolute_photo_urls_are_passed_through_untouched(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $order = $this->orderFor($buyer, shopPhoto: 'https://cdn.example.com/shops/city-unlock.png');

        Sanctum::actingAs($buyer);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.seller.store_logo', 'https://cdn.example.com/shops/city-unlock.png');
    }

    public function test_store_logo_is_null_when_the_seller_has_no_photo(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $order = $this->orderFor($buyer, shopPhoto: null);

        Sanctum::actingAs($buyer);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.seller.store_logo', null);
    }

    public function test_the_order_list_carries_the_logo_too(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $this->orderFor($buyer, shopPhoto: 'sellers/documents/shop-front.jpg');

        Sanctum::actingAs($buyer);

        $logo = $this->getJson('/api/v1/orders')->assertOk()->json('data.0.seller.store_logo');
        $this->assertStringContainsString('sellers/documents/shop-front.jpg', (string) $logo);
    }

    private function orderFor(User $buyer, ?string $shopPhoto, ?string $avatar = null): Order
    {
        $seller = User::factory()->create(['role' => UserRole::Seller, 'avatar' => $avatar]);

        SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => 'City Unlock',
            'store_name' => 'City Unlock',
            'slug' => 'city-unlock',
            'status' => SellerStatus::Approved,
            'shop_photo' => $shopPhoto,
        ]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'receiver_name' => 'Kofi amoah',
            'receiver_phone' => '0539790093',
            'region' => 'Western North',
            'city' => 'Sefwi Bekwai',
            'subtotal' => 20,
            'shipping_cost' => 0,
            'total' => 20,
        ]);

        return Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_channel' => PaymentChannel::Direct,
            'receiver_name' => 'Kofi amoah',
            'receiver_phone' => '0539790093',
            'region' => 'Western North',
            'city' => 'Sefwi Bekwai',
            'subtotal' => 20,
            'shipping_cost' => 0,
            'commission_amount' => 0,
            'total' => 20,
        ]);
    }
}
