<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUnprocessedOrdersTest extends TestCase
{
    use RefreshDatabase;

    private function makeStalePaidItem(User $buyer, User $seller, int $hoursAgo = 25): OrderItem
    {
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Stale Phone',
            'slug' => 'stale-phone-'.uniqid(),
            'price' => 100,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
        ]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'receiver_name' => 'Test Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 100,
            'shipping_cost' => 10,
            'total' => 110,
        ]);

        $order = Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'payment_channel' => PaymentChannel::Marketplace,
            'receiver_name' => 'Test Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 100,
            'shipping_cost' => 10,
            'commission_amount' => 5,
            'total' => 110,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'Stale Phone',
            'quantity' => 1,
            'unit_price' => 100,
            'commission_rate' => 5,
            'commission_amount' => 5,
            'seller_amount' => 95,
            'status' => OrderStatus::Pending,
        ]);

        Payment::create([
            'checkout_id' => $checkout->id,
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'channel' => PaymentChannel::Marketplace,
            'method' => 'wallet',
            'amount' => 110,
            'status' => PaymentStatus::Paid,
            'paid_at' => now()->subHours($hoursAgo),
        ]);

        Wallet::create([
            'user_id' => $seller->id,
            'available_balance' => 0,
            'pending_balance' => 95,
            'total_earnings' => 95,
            'withdrawn_amount' => 0,
        ]);

        Wallet::create([
            'user_id' => $buyer->id,
            'available_balance' => 0,
            'pending_balance' => 0,
            'total_earnings' => 0,
            'withdrawn_amount' => 0,
        ]);

        return $item;
    }

    public function test_admin_can_view_stale_unprocessed_queue(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $this->makeStalePaidItem($buyer, $seller, 30);

        $this->actingAs($admin)
            ->get(route('admin.orders.unprocessed'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/orders/unprocessed')
                ->where('count', 1));
    }

    public function test_admin_cancel_refunds_buyer_wallet(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $item = $this->makeStalePaidItem($buyer, $seller, 30);

        $this->actingAs($admin)
            ->post(route('admin.orders.unprocessed.cancel', $item), [
                'reason' => 'Admin cancelled: seller inactive',
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertSame(OrderStatus::Cancelled, $item->status);

        $buyerWallet = Wallet::where('user_id', $buyer->id)->first();
        // Product line total refunded to buyer wallet
        $this->assertEquals(100.0, (float) $buyerWallet->available_balance);
    }

    public function test_fresh_pending_item_is_not_in_stale_queue(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $this->makeStalePaidItem($buyer, $seller, 2);

        $count = app(OrderService::class)->staleUnprocessedItemsQuery(24)->count();
        $this->assertSame(0, $count);
    }
}
