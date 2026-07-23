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
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAwaitingDirectPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_unpaid_direct_orders(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Direct Shop',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);

        Product::create([
            'seller_id' => $seller->id,
            'name' => 'Direct Item',
            'slug' => 'direct-item-admin',
            'price' => 50,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
        ]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHKTESTDIRECT1',
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0500000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 50,
            'shipping_cost' => 0,
            'total' => 50,
        ]);

        $pendingDirect = Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => 'CSTESTDIRECT1',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_channel' => PaymentChannel::Direct,
            'payment_method' => 'direct',
            'subtotal' => 50,
            'shipping_cost' => 0,
            'commission_amount' => 0,
            'total' => 50,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0500000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
        ]);

        Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => 'CSTESTPAIDMKT1',
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Processing,
            'payment_status' => PaymentStatus::Paid,
            'payment_channel' => PaymentChannel::Marketplace,
            'payment_method' => 'momo',
            'subtotal' => 50,
            'shipping_cost' => 0,
            'commission_amount' => 5,
            'total' => 50,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0500000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
        ]);

        $this->withoutVite();

        $this->actingAs($admin)
            ->get(route('admin.orders.awaiting-direct'))
            ->assertOk()
            ->assertInertia(fn ($assert) => $assert
                ->component('admin/orders/awaiting-direct')
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $pendingDirect->id)
                ->where('count', 1)
            );
    }
}
