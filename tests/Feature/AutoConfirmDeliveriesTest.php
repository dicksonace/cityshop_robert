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
use App\Support\DeliveryConfirmationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoConfirmDeliveriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: OrderItem}
     */
    private function awaitingItem(?\DateTimeInterface $awaitingAt = null): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Auto Confirm Store',
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
            'name' => 'Auto Bike',
            'slug' => 'auto-'.uniqid(),
            'price' => 100,
            'quantity' => 3,
            'status' => ProductStatus::Approved,
        ]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 100,
            'shipping_cost' => 0,
            'total' => 100,
        ]);

        $order = Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::AwaitingConfirmation,
            'payment_status' => PaymentStatus::Paid,
            'payment_channel' => PaymentChannel::Direct,
            'payment_method' => 'momo',
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 100,
            'shipping_cost' => 0,
            'commission_amount' => 0,
            'total' => 100,
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'Auto Bike',
            'quantity' => 1,
            'unit_price' => 100,
            'commission_amount' => 0,
            'seller_amount' => 100,
            'status' => OrderStatus::AwaitingConfirmation,
            'awaiting_confirmation_at' => $awaitingAt ?? now(),
        ]);

        return [$buyer, $item->fresh()];
    }

    public function test_remaining_label_formats_days_and_hours(): void
    {
        config(['marketplace.auto_confirm_delivery_days' => 21]);

        // 21-day window, started 22 hours ago → ~20 days, 2 hours left.
        [, $item] = $this->awaitingItem(now()->subHours(22));

        $this->assertSame('20 days, 2 hours', DeliveryConfirmationPolicy::remainingLabel($item));
    }

    public function test_command_auto_confirms_expired_items(): void
    {
        [, $expired] = $this->awaitingItem(now()->subDays(DeliveryConfirmationPolicy::days())->subHour());
        [, $fresh] = $this->awaitingItem(now()->subDay());

        $this->artisan('orders:auto-confirm-deliveries')
            ->assertSuccessful();

        $this->assertSame(OrderStatus::Delivered, $expired->fresh()->status);
        $this->assertSame(OrderStatus::AwaitingConfirmation, $fresh->fresh()->status);
    }

    public function test_order_show_includes_auto_confirm_countdown(): void
    {
        $this->withoutVite();
        [$buyer, $item] = $this->awaitingItem(now()->subDay());

        $this->actingAs($buyer)
            ->get(route('orders.show', ['order' => $item->order_id, 'package' => 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/order-show')
                ->where('order.items.0.auto_confirm_in', fn ($value) => is_string($value) && $value !== '')
            );
    }
}
