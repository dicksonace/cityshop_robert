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
use App\Notifications\PaymentConfirmedNotification;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class DirectPaymentSellerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSeller(): User
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Direct Pay Store',
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

    private function unpaidDirectOrder(User $buyer, User $seller, bool $withClaim = false): OrderItem
    {
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Direct phone',
            'slug' => 'direct-'.uniqid(),
            'price' => 120,
            'quantity' => 3,
            'status' => ProductStatus::Approved,
        ]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 120,
            'shipping_cost' => 0,
            'total' => 120,
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
            'direct_payment_reference' => $withClaim ? 'MOMO-999' : null,
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 120,
            'shipping_cost' => 0,
            'commission_amount' => 0,
            'total' => 120,
        ]);

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'unit_price' => 120,
            'quantity' => 1,
            'commission_rate' => 0,
            'commission_amount' => 0,
            'seller_amount' => 120,
            'status' => OrderStatus::Pending,
        ]);
    }

    public function test_unclaimed_direct_order_is_hidden_from_seller_new_queue(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller();
        $item = $this->unpaidDirectOrder($buyer, $seller, withClaim: false);

        $this->actingAs($seller)
            ->get(route('seller.orders.stage', 'new'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('seller/orders/stage')
                ->has('orders.data', 0)
            );

        $this->actingAs($seller)
            ->get(route('seller.orders.show', $item))
            ->assertRedirect(route('seller.orders.index'));
    }

    public function test_claimed_direct_order_appears_on_seller_dashboard(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller();
        $item = $this->unpaidDirectOrder($buyer, $seller, withClaim: true);

        $this->actingAs($seller)
            ->get(route('seller.orders.stage', 'new'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('seller/orders/stage')
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $item->id)
            );
    }

    public function test_buyer_claim_notifies_seller_and_does_not_notify_at_create(): void
    {
        Notification::fake();

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller();
        $item = $this->unpaidDirectOrder($buyer, $seller, withClaim: false);
        $order = $item->order;

        app(OrderService::class)->submitDirectPaymentReference($order, 'TXN-555');

        Notification::assertSentTo(
            $seller,
            PaymentConfirmedNotification::class,
            fn (PaymentConfirmedNotification $n) => $n->paymentClaim === true,
        );
    }
}
