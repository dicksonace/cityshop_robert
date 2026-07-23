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
use App\Support\BuyerOrderPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerOrderWindowTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSeller(): User
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Window Store',
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

    /**
     * @return array{0: User, 1: Order, 2: OrderItem, 3: Checkout}
     */
    private function deliveredOrder(User $buyer, ?\DateTimeInterface $createdAt = null): array
    {
        $seller = $this->approvedSeller();
        $createdAt = $createdAt ?? now();

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Window product',
            'slug' => 'win-'.uniqid(),
            'price' => 40,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
        ]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 40,
            'shipping_cost' => 0,
            'total' => 40,
        ]);
        $checkout->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        $order = Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Delivered,
            'payment_status' => PaymentStatus::Paid,
            'payment_channel' => PaymentChannel::Marketplace,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 40,
            'shipping_cost' => 0,
            'commission_amount' => 0,
            'total' => 40,
        ]);
        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'Window product',
            'quantity' => 1,
            'unit_price' => 40,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'seller_amount' => 40,
            'status' => OrderStatus::Delivered,
        ]);

        return [$buyer, $order->fresh(), $item, $checkout->fresh()];
    }

    public function test_recent_orders_appear_in_my_orders(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        [, $order, , $checkout] = $this->deliveredOrder($buyer, now()->subMonth());

        $this->actingAs($buyer)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/orders')
                ->has('purchases.data', 1)
                ->where('purchases.data.0.id', $checkout->id)
                ->where('purchases.data.0.packages.0.can_refund', true)
                ->where('purchases.data.0.packages.0.id', $order->id));
    }

    public function test_orders_older_than_two_months_disappear_from_my_orders(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $this->deliveredOrder($buyer, now()->subMonths(BuyerOrderPolicy::months())->subDay());

        $this->actingAs($buyer)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/orders')
                ->has('purchases.data', 0)
                ->where('counts.all', 0));
    }

    public function test_buyer_cannot_request_refund_after_two_months(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        [, $order, $item] = $this->deliveredOrder(
            $buyer,
            now()->subMonths(BuyerOrderPolicy::months())->subDay(),
        );

        $this->actingAs($buyer)
            ->from(route('orders.show', ['order' => $order->id, 'package' => 1]))
            ->post(route('orders.disputes.store', $order), [
                'order_item_id' => $item->id,
                'reason' => 'damaged_item',
                'description' => 'Broken after two months window.',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseCount('disputes', 0);
    }

    public function test_buyer_can_request_refund_within_two_months(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        [, $order, $item] = $this->deliveredOrder($buyer, now()->subWeeks(3));

        $this->actingAs($buyer)
            ->post(route('orders.disputes.store', $order), [
                'order_item_id' => $item->id,
                'reason' => 'damaged_item',
                'description' => 'Item arrived damaged.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('disputes', 1);
    }
}
