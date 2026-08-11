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
use Laravel\Sanctum\Sanctum;
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

    public function test_seller_can_save_delivery_details_without_changing_status(): void
    {
        [$seller, $item] = $this->makeItem('momo', OrderStatus::AwaitingConfirmation);

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $item), [
                'vehicle_number' => 'GR 1234-20',
                'driver_phone' => '0240000000',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertRedirect(route('seller.orders.show', $item));

        $item->refresh();
        $this->assertSame(OrderStatus::AwaitingConfirmation, $item->status);
        $this->assertSame('GR 1234-20', $item->vehicle_number);
        $this->assertSame('0240000000', $item->driver_phone);
    }

    public function test_seller_can_save_delivery_details_via_post_without_405(): void
    {
        [$seller, $item] = $this->makeItem('momo', OrderStatus::AwaitingConfirmation);

        $this->actingAs($seller)
            ->post(route('seller.orders.update', $item), [
                'vehicle_number' => 'GR 999-20',
                'driver_phone' => '0241111111',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('seller.orders.show', $item));

        $item->refresh();
        $this->assertSame('GR 999-20', $item->vehicle_number);
        $this->assertSame('0241111111', $item->driver_phone);
    }

    public function test_saving_delivery_details_with_same_status_does_not_fail_transition(): void
    {
        [$seller, $item] = $this->makeItem('momo', OrderStatus::Shipped);

        $this->actingAs($seller)
            ->patch(route('seller.orders.update', $item), [
                'status' => 'shipped',
                'vehicle_number' => 'GS 555-21',
                'driver_phone' => '0201111111',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('seller.orders.show', $item));

        $item->refresh();
        $this->assertSame(OrderStatus::Shipped, $item->status);
        $this->assertSame('GS 555-21', $item->vehicle_number);
        $this->assertSame('0201111111', $item->driver_phone);
    }

    public function test_buyer_api_includes_delivery_details(): void
    {
        [, $item] = $this->makeItem('momo', OrderStatus::Shipped);
        $item->update([
            'vehicle_number' => 'GR 1234-20',
            'driver_phone' => '0240000000',
            'package_image' => 'order-packages/box.jpg',
        ]);

        Sanctum::actingAs($item->order->buyer);

        $response = $this->getJson('/api/v1/orders/'.$item->order_id)->assertOk();

        $response->assertJsonPath('data.items.0.vehicle_number', 'GR 1234-20')
            ->assertJsonPath('data.items.0.driver_phone', '0240000000')
            ->assertJsonPath('data.items.0.package_image', 'order-packages/box.jpg');

        $this->assertStringContainsString(
            'order-packages/box.jpg',
            (string) $response->json('data.items.0.package_image_url'),
        );
    }
}
