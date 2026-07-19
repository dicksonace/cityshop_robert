<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use App\Notifications\DirectPaymentRejectedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SellerDirectPaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSeller(): User
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Pay Review Store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'accept_direct_payments' => true,
            'accept_marketplace_payments' => true,
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

    private function pendingDirectOrder(User $buyer, User $seller): Order
    {
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Electric bike',
            'slug' => 'bike-'.uniqid(),
            'price' => 300,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
        ]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Western North',
            'city' => 'Bibiani',
            'subtotal' => 300,
            'shipping_cost' => 0,
            'total' => 300,
        ]);

        $order = Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_channel' => PaymentChannel::Direct,
            'direct_payment_reference' => 'MOMO-12345',
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Western North',
            'city' => 'Bibiani',
            'subtotal' => 300,
            'shipping_cost' => 0,
            'commission_amount' => 0,
            'total' => 300,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 300,
            'quantity' => 1,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'seller_amount' => 300,
            'status' => OrderStatus::Pending,
        ]);

        return $order->fresh();
    }

    public function test_seller_can_confirm_manual_payment_claim(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller();
        $order = $this->pendingDirectOrder($buyer, $seller);

        $this->actingAs($seller)
            ->post(route('seller.orders.confirm-direct-payment', $order))
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(OrderStatus::Processing, $order->status);
        $this->assertNotNull($order->direct_payment_confirmed_at);
    }

    public function test_seller_can_reject_manual_payment_claim_so_buyer_can_resubmit(): void
    {
        Notification::fake();

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller();
        $order = $this->pendingDirectOrder($buyer, $seller);

        $this->actingAs($seller)
            ->post(route('seller.orders.reject-direct-payment', $order), [
                'reason' => 'Money not received on MoMo',
            ])
            ->assertRedirect();

        $order->refresh();
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertNull($order->direct_payment_reference);
        $this->assertNull($order->direct_payment_proof_path);
        $this->assertSame('Money not received on MoMo', $order->direct_payment_rejection_reason);

        Notification::assertSentTo($buyer, DirectPaymentRejectedNotification::class);
    }

    public function test_seller_cannot_reject_without_a_payment_claim(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller();
        $order = $this->pendingDirectOrder($buyer, $seller);
        $order->update([
            'direct_payment_reference' => null,
            'direct_payment_proof_path' => null,
        ]);

        $this->actingAs($seller)
            ->from(route('seller.orders.index'))
            ->post(route('seller.orders.reject-direct-payment', $order), [
                'reason' => 'Money not received',
            ])
            ->assertSessionHasErrors('reason');
    }
}
