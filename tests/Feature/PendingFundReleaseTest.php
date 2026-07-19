<?php

namespace Tests\Feature;

use App\Enums\FundsReleaseStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingFundReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function makeAwaitingItem(): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Fund Gate Phone',
            'slug' => 'fund-gate-'.uniqid(),
            'price' => 100,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
        ]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::AwaitingConfirmation,
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
            'status' => OrderStatus::AwaitingConfirmation,
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
            'product_name' => 'Fund Gate Phone',
            'quantity' => 1,
            'unit_price' => 100,
            'commission_rate' => 5,
            'commission_amount' => 5,
            'seller_amount' => 95,
            'status' => OrderStatus::AwaitingConfirmation,
        ]);

        Wallet::create([
            'user_id' => $seller->id,
            'available_balance' => 0,
            'pending_balance' => 105,
            'total_earnings' => 105,
            'withdrawn_amount' => 0,
        ]);

        return compact('buyer', 'seller', 'admin', 'order', 'item');
    }

    public function test_buyer_confirm_keeps_funds_pending_for_admin(): void
    {
        ['buyer' => $buyer, 'seller' => $seller, 'item' => $item] = $this->makeAwaitingItem();

        $this->actingAs($buyer)
            ->post(route('orders.confirm-delivery', [$item->order_id, $item->id]))
            ->assertRedirect();

        $item->refresh();
        $wallet = Wallet::where('user_id', $seller->id)->first();

        $this->assertSame(OrderStatus::Delivered, $item->status);
        $this->assertSame(FundsReleaseStatus::Pending, $item->funds_release_status);
        $this->assertEquals(105.0, (float) $wallet->pending_balance);
        $this->assertEquals(0.0, (float) $wallet->available_balance);
    }

    public function test_admin_approve_releases_pending_to_available(): void
    {
        ['buyer' => $buyer, 'seller' => $seller, 'admin' => $admin, 'item' => $item] = $this->makeAwaitingItem();

        app(OrderService::class)->confirmBuyerDelivery($item);

        $this->actingAs($admin)
            ->post(route('admin.pending-funds.approve', $item->id))
            ->assertRedirect();

        $item->refresh();
        $wallet = Wallet::where('user_id', $seller->id)->first();

        $this->assertSame(FundsReleaseStatus::Released, $item->funds_release_status);
        $this->assertEquals(10.0, (float) $wallet->pending_balance);
        $this->assertEquals(95.0, (float) $wallet->available_balance);
        $this->assertSame($admin->id, $item->funds_reviewed_by);
    }

    public function test_admin_reject_holds_funds_and_opens_dispute(): void
    {
        ['buyer' => $buyer, 'admin' => $admin, 'item' => $item] = $this->makeAwaitingItem();

        app(OrderService::class)->confirmBuyerDelivery($item);

        $this->actingAs($admin)
            ->post(route('admin.pending-funds.reject', $item->id), [
                'admin_notes' => 'Suspicious delivery claim',
            ])
            ->assertRedirect();

        $item->refresh();

        $this->assertSame(FundsReleaseStatus::Held, $item->funds_release_status);
        $this->assertTrue(Dispute::where('order_item_id', $item->id)->where('status', 'open')->exists());
    }
}
