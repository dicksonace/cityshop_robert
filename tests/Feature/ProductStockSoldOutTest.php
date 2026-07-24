<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProductStockSoldOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_order_sets_quantity_zero_and_notifies_seller(): void
    {
        Notification::fake();

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Stock Store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Electric bike',
            'slug' => 'electric-bike-'.uniqid(),
            'price' => 150,
            'quantity' => 1,
            'status' => ProductStatus::Approved,
            'is_preorder' => false,
        ]);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_channel' => PaymentChannel::Marketplace,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 150,
            'shipping_cost' => 0,
            'commission_amount' => 7.5,
            'total' => 150,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'Electric bike',
            'quantity' => 1,
            'unit_price' => 150,
            'commission_rate' => 5,
            'commission_amount' => 7.5,
            'seller_amount' => 142.5,
            'status' => OrderStatus::Pending,
        ]);

        app(OrderService::class)->fulfillPaidOrder(
            $order->fresh('items'),
            'ref-stock-test',
            skipCheckoutUpdate: true,
            skipBuyerNotify: true,
        );

        $this->assertSame(0, (int) $product->fresh()->quantity);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $seller->id,
            'type' => 'product_out_of_stock',
            'title' => 'Update stock — item sold out',
        ]);
    }
}
