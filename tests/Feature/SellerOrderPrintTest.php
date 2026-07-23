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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerOrderPrintTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSeller(string $storeName = 'Print Test Store'): User
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => $storeName,
            'business_name' => $storeName,
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

    private function makeOrderItem(User $buyer, User $seller): OrderItem
    {
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Wireless earbuds',
            'slug' => 'buds-'.uniqid(),
            'price' => 75,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
        ]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Paid,
            'receiver_name' => 'Kofi Buyer',
            'receiver_phone' => '0539790093',
            'region' => 'Ashanti',
            'city' => 'Kumasi',
            'subtotal' => 150,
            'shipping_cost' => 0,
            'total' => 150,
        ]);

        $order = Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Paid,
            'payment_channel' => PaymentChannel::Marketplace,
            'payment_method' => 'momo',
            'receiver_name' => 'Kofi Buyer',
            'receiver_phone' => '0539790093',
            'region' => 'Ashanti',
            'city' => 'Kumasi',
            'subtotal' => 150,
            'shipping_cost' => 0,
            'commission_amount' => 7.5,
            'total' => 150,
        ]);

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'Wireless earbuds',
            'quantity' => 2,
            'unit_price' => 75,
            'commission_amount' => 7.5,
            'seller_amount' => 142.5,
            'status' => OrderStatus::Pending,
        ]);
    }

    public function test_seller_can_view_packing_slip_and_download_pdf(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller('Ace Gadgets');
        $item = $this->makeOrderItem($buyer, $seller);

        $this->actingAs($seller)
            ->get(route('seller.orders.print', $item))
            ->assertOk()
            ->assertSee('Packing slip', false)
            ->assertSee($item->order->order_number, false)
            ->assertSee('Wireless earbuds', false)
            ->assertSee('Kofi Buyer', false)
            ->assertSee('Ace Gadgets', false);

        $pdf = $this->actingAs($seller)->get(route('seller.orders.pdf', $item));
        $pdf->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
    }

    public function test_seller_cannot_print_another_sellers_order(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $owner = $this->approvedSeller('Owner Store');
        $intruder = $this->approvedSeller('Intruder Store');
        $item = $this->makeOrderItem($buyer, $owner);

        $this->actingAs($intruder)
            ->get(route('seller.orders.print', $item))
            ->assertForbidden();
    }
}
