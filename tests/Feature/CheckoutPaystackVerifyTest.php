<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\User;
use App\Services\CheckoutPaymentVerifier;
use App\Services\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CheckoutPaystackVerifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_funding_defaults_enabled_when_unset(): void
    {
        $settings = PlatformSettings::manualFundingAccounts();

        $this->assertTrue($settings['enabled']);
        $this->assertNotEmpty($settings['accounts']);
        $this->assertContains('mtn', collect($settings['accounts'])->pluck('network')->all());
    }

    public function test_buyer_cannot_verify_another_buyers_checkout(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $other = User::factory()->create(['role' => UserRole::Buyer]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 15,
            'shipping_cost' => 0,
            'total' => 15,
        ]);

        Sanctum::actingAs($other);

        $this->postJson("/api/v1/checkouts/{$checkout->id}/pay/verify", ['reference' => 'CSH-fake'])
            ->assertForbidden();
    }

    public function test_verify_rejects_amount_mismatch(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer, 'email' => 'buyer@test.com']);
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 15,
            'shipping_cost' => 0,
            'total' => 15,
        ]);

        Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_channel' => PaymentChannel::Marketplace,
            'payment_reference' => 'CSH-test-ref',
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 15,
            'shipping_cost' => 0,
            'commission_amount' => 0,
            'total' => 15,
        ]);

        Http::fake([
            'api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'amount' => 100, // underpay: 1.00 GHS vs 15.00
                    'currency' => 'GHS',
                    'reference' => 'CSH-test-ref',
                    'metadata' => ['checkout_id' => $checkout->id],
                ],
            ]),
        ]);

        app(CheckoutPaymentVerifier::class)->rememberPending($checkout, 'CSH-test-ref', 1500);

        Sanctum::actingAs($buyer);

        $this->postJson("/api/v1/checkouts/{$checkout->id}/pay/verify", ['reference' => 'CSH-test-ref'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reference']);
    }
}
