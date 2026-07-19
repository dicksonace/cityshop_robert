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
use App\Models\Payment;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use App\Models\Wallet;
use App\Support\OrderCancellation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerOrderCancelTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSeller(): User
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Cancel Test Store',
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

    public function test_seller_can_cancel_pending_order_and_refund_buyer(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller();

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Out of stock phone',
            'slug' => 'oos-'.uniqid(),
            'price' => 80,
            'quantity' => 0,
            'status' => ProductStatus::Approved,
        ]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 80,
            'shipping_cost' => 0,
            'total' => 80,
        ]);

        $order = Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'payment_channel' => PaymentChannel::Marketplace,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 80,
            'shipping_cost' => 0,
            'commission_amount' => 4,
            'total' => 80,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'Out of stock phone',
            'quantity' => 1,
            'unit_price' => 80,
            'commission_rate' => 5,
            'commission_amount' => 4,
            'seller_amount' => 76,
            'status' => OrderStatus::Pending,
        ]);

        Payment::create([
            'checkout_id' => $checkout->id,
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'channel' => PaymentChannel::Marketplace,
            'method' => 'wallet',
            'amount' => 80,
            'status' => PaymentStatus::Paid,
            'paid_at' => now()->subHour(),
        ]);

        Wallet::create([
            'user_id' => $seller->id,
            'available_balance' => 0,
            'pending_balance' => 76,
            'total_earnings' => 76,
            'withdrawn_amount' => 0,
        ]);

        Wallet::create([
            'user_id' => $buyer->id,
            'available_balance' => 0,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        $response = $this->actingAs($seller)
            ->post(route('seller.orders.reject', $item), [
                'cancellation_code' => 'out_of_stock',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('seller.orders.stage', 'cancelled'));

        $item->refresh();
        $this->assertSame(OrderStatus::Cancelled, $item->status);
        $this->assertSame(OrderCancellation::BY_SELLER, $item->cancelled_by);
        $this->assertSame('out_of_stock', $item->cancellation_code);
        $this->assertSame(OrderCancellation::REFUND_COMPLETED, $item->refund_status);
        $this->assertEquals(80.0, (float) Wallet::where('user_id', $buyer->id)->value('available_balance'));
        $this->assertEquals(1, Product::find($product->id)->quantity);
    }

    public function test_seller_cannot_cancel_shipped_order(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller();

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Shipped item',
            'slug' => 'ship-'.uniqid(),
            'price' => 50,
            'quantity' => 1,
            'status' => ProductStatus::Approved,
        ]);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Shipped,
            'payment_status' => PaymentStatus::Paid,
            'payment_channel' => PaymentChannel::Marketplace,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 50,
            'shipping_cost' => 0,
            'total' => 50,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'Shipped item',
            'quantity' => 1,
            'unit_price' => 50,
            'commission_rate' => 5,
            'commission_amount' => 2.5,
            'seller_amount' => 47.5,
            'status' => OrderStatus::Shipped,
        ]);

        $this->actingAs($seller)
            ->from(route('seller.orders.show', $item))
            ->post(route('seller.orders.reject', $item), [
                'cancellation_code' => 'out_of_stock',
            ])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Shipped, $item->fresh()->status);
    }

    public function test_cancelling_unpaid_direct_order_marks_order_cancelled_not_pending(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller();

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Electric bike',
            'slug' => 'bike-'.uniqid(),
            'price' => 150,
            'quantity' => 2,
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
            'subtotal' => 150,
            'shipping_cost' => 200,
            'total' => 350,
        ]);

        $order = Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_channel' => PaymentChannel::Direct,
            'payment_method' => 'direct',
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Western North',
            'city' => 'Bibiani',
            'subtotal' => 150,
            'shipping_cost' => 200,
            'commission_amount' => 0,
            'total' => 350,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'Electric bike',
            'quantity' => 1,
            'unit_price' => 150,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'seller_amount' => 150,
            'status' => OrderStatus::Pending,
        ]);

        $this->actingAs($seller)
            ->post(route('seller.orders.reject', $item), [
                'cancellation_code' => 'other',
                'rejection_reason' => 'not pay',
            ])
            ->assertSessionHasNoErrors();

        $order->refresh();
        $checkout->refresh();

        $this->assertSame(OrderStatus::Cancelled, $item->fresh()->status);
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertSame(PaymentStatus::Failed, $order->payment_status);
        $this->assertSame(OrderStatus::Cancelled, $checkout->status);
        $this->assertSame(PaymentStatus::Failed, $checkout->payment_status);
    }
}
