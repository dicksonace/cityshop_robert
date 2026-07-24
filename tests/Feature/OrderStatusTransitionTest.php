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
use App\Models\StoreCustomization;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function makeItem(string $paymentMethod, OrderStatus $status): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Flow Store',
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

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Item',
            'slug' => 'item-'.uniqid(),
            'price' => 100,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
        ]);

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Processing,
            'payment_status' => $paymentMethod === 'cash' ? PaymentStatus::Pending : PaymentStatus::Paid,
            'payment_channel' => PaymentChannel::Marketplace,
            'payment_method' => $paymentMethod,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 100,
            'shipping_cost' => 0,
            'total' => 100,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'Item',
            'quantity' => 1,
            'unit_price' => 100,
            'commission_rate' => 5,
            'commission_amount' => 5,
            'seller_amount' => 95,
            'status' => $status,
        ]);

        return [$seller, $item];
    }

    public function test_paid_order_skips_call_buyer_and_goes_to_packing(): void
    {
        [$seller, $item] = $this->makeItem('momo', OrderStatus::Processing);

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $item), ['status' => 'call_confirmed'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(OrderStatus::Processing, $item->fresh()->status);

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $item), ['status' => 'packed'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(OrderStatus::Packed, $item->fresh()->status);
    }

    public function test_cod_order_requires_call_buyer_before_packing(): void
    {
        [$seller, $item] = $this->makeItem('cash', OrderStatus::Processing);

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $item), ['status' => 'packed'])
            ->assertSessionHas('error');

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $item), ['status' => 'call_confirmed'])
            ->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::CallConfirmed, $item->fresh()->status);
    }

    public function test_call_stage_lists_only_cod_orders(): void
    {
        [, $codItem] = $this->makeItem('cash', OrderStatus::CallConfirmed);
        [, $paidItem] = $this->makeItem('momo', OrderStatus::CallConfirmed);
        $seller = User::find($codItem->seller_id);

        // Paid stuck item belongs to a different seller — recreate under same seller
        $paidItem->update(['seller_id' => $codItem->seller_id]);
        $paidItem->order->update(['seller_id' => $codItem->seller_id]);

        $this->actingAs($seller)
            ->get(route('seller.orders.stage', 'call'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('seller/orders/stage')
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $codItem->id));
    }
}
