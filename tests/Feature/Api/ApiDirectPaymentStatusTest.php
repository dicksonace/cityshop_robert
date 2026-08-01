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

/**
 * Direct seller payments stay `pending` until the seller confirms the transfer,
 * so the app needs the proof fields to tell "buyer has not paid" apart from
 * "buyer paid, seller has not confirmed yet".
 */
class ApiDirectPaymentStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_direct_order_is_not_marked_as_submitted(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $order = $this->directOrder($buyer);

        Sanctum::actingAs($buyer);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.direct_payment_submitted', false)
            ->assertJsonPath('data.direct_payment_confirmed_at', null);
    }

    public function test_a_transaction_id_marks_the_payment_as_submitted(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $order = $this->directOrder($buyer, ['direct_payment_reference' => 'MP2608010001']);

        Sanctum::actingAs($buyer);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.direct_payment_submitted', true)
            ->assertJsonPath('data.direct_payment_reference', 'MP2608010001');
    }

    public function test_a_screenshot_alone_marks_the_payment_as_submitted(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $order = $this->directOrder($buyer, [
            'direct_payment_proof_path' => 'direct-payment-proofs/proof.jpg',
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('data.0.direct_payment_submitted', true);
    }

    public function test_the_confirmation_timestamp_is_exposed_once_the_seller_confirms(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $order = $this->directOrder($buyer, [
            'direct_payment_reference' => 'MP2608010001',
            'direct_payment_confirmed_at' => now(),
            'payment_status' => PaymentStatus::Paid,
        ]);

        Sanctum::actingAs($buyer);

        $confirmed = $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->json('data.direct_payment_confirmed_at');

        $this->assertIsString($confirmed);
    }

    public function test_a_rejection_reason_reaches_the_app(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $order = $this->directOrder($buyer, [
            'direct_payment_reference' => 'MP2608010001',
            'direct_payment_rejection_reason' => 'Amount does not match',
        ]);

        Sanctum::actingAs($buyer);

        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.direct_payment_submitted', true)
            ->assertJsonPath('data.direct_payment_rejection_reason', 'Amount does not match');
    }

    private function directOrder(User $buyer, array $overrides = []): Order
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        SellerProfile::create([
            'user_id' => $seller->id,
            'business_name' => 'City Unlock',
            'store_name' => 'City Unlock',
            'slug' => 'city-unlock-'.$seller->id,
            'status' => SellerStatus::Approved,
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
            'subtotal' => 350,
            'shipping_cost' => 0,
            'total' => 350,
        ]);

        return Order::create(array_merge([
            'checkout_id' => $checkout->id,
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Pending,
            'payment_channel' => PaymentChannel::Direct,
            'payment_method' => 'momo',
            'receiver_name' => 'Kofi amoah',
            'receiver_phone' => '0539790093',
            'region' => 'Western North',
            'city' => 'Sefwi Bekwai',
            'subtotal' => 350,
            'shipping_cost' => 0,
            'commission_amount' => 0,
            'total' => 350,
        ], $overrides));
    }
}
