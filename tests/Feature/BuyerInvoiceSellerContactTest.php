<?php

namespace Tests\Feature;

use App\Enums\InvoiceType;
use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Checkout;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerInvoiceSellerContactTest extends TestCase
{
    use RefreshDatabase;

    private function approvedSeller(string $storeName, string $businessAddress): User
    {
        $seller = User::factory()->create([
            'role' => UserRole::Seller,
            'name' => $storeName.' Owner',
            'mobile' => '0248000111',
            'city' => 'Accra',
            'region' => 'Greater Accra',
            'residential_address' => 'Backup Street',
        ]);

        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => $storeName,
            'business_name' => $storeName.' Ltd',
            'business_address' => $businessAddress,
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

        return $seller->fresh(['sellerProfile']);
    }

    public function test_master_invoice_includes_store_name_and_address(): void
    {
        $this->withoutVite();

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller('City Gadget Hub', '15 Spintex Road, Accra');

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'USB Cable',
            'slug' => 'usb-'.uniqid(),
            'price' => 25,
            'quantity' => 10,
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
            'subtotal' => 25,
            'shipping_cost' => 0,
            'total' => 25,
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
            'subtotal' => 25,
            'shipping_cost' => 0,
            'commission_amount' => 1.25,
            'total' => 25,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'USB Cable',
            'quantity' => 1,
            'unit_price' => 25,
            'commission_amount' => 1.25,
            'seller_amount' => 23.75,
            'status' => OrderStatus::Pending,
        ]);

        $master = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'checkout_id' => $checkout->id,
            'order_id' => null,
            'user_id' => $buyer->id,
            'type' => InvoiceType::CustomerMaster,
            'line_items' => [
                [
                    'seller' => 'City Gadget Hub',
                    'product_name' => 'USB Cable',
                    'quantity' => 1,
                    'unit_price' => 25,
                    'total' => 25,
                ],
            ],
            'subtotal' => 25,
            'commission_amount' => 1.25,
            'shipping_cost' => 0,
            'total' => 25,
            'payment_status' => PaymentStatus::Paid->value,
            'issued_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->get(route('invoices.show', $master))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/invoice-show')
                ->where('sellerContacts.0.store_name', 'City Gadget Hub')
                ->where('sellerContacts.0.address', '15 Spintex Road, Accra')
                ->where('sellerContact.store_name', 'City Gadget Hub')
                ->where('sellerContact.address', '15 Spintex Road, Accra')
            );
        $this->actingAs($buyer)
            ->get(route('invoices.print', $master))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($buyer)
            ->get(route('invoices.pdf', $master))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename="CityShop-Invoice-'.$master->invoice_number.'.pdf"');
    }

    public function test_seller_invoice_includes_store_name_and_address(): void
    {
        $this->withoutVite();

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller('Phone World', '8 Oxford Street');

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Case',
            'slug' => 'case-'.uniqid(),
            'price' => 40,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
        ]);

        $checkout = Checkout::create([
            'checkout_number' => 'CHK'.uniqid(),
            'buyer_id' => $buyer->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Paid,
            'receiver_name' => 'Ama Buyer',
            'receiver_phone' => '0539790093',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 40,
            'shipping_cost' => 0,
            'total' => 40,
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
            'receiver_name' => 'Ama Buyer',
            'receiver_phone' => '0539790093',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => 40,
            'shipping_cost' => 0,
            'commission_amount' => 2,
            'total' => 40,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'Case',
            'quantity' => 1,
            'unit_price' => 40,
            'commission_amount' => 2,
            'seller_amount' => 38,
            'status' => OrderStatus::Pending,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'checkout_id' => $checkout->id,
            'order_id' => $order->id,
            'user_id' => $buyer->id,
            'type' => InvoiceType::Customer,
            'line_items' => [
                [
                    'product_name' => 'Case',
                    'quantity' => 1,
                    'unit_price' => 40,
                    'total' => 40,
                ],
            ],
            'subtotal' => 40,
            'commission_amount' => 2,
            'shipping_cost' => 0,
            'total' => 40,
            'payment_status' => PaymentStatus::Paid->value,
            'issued_at' => now(),
        ]);

        $this->actingAs($buyer)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/invoice-show')
                ->where('sellerContacts.0.store_name', 'Phone World')
                ->where('sellerContacts.0.address', '8 Oxford Street')
            );
    }
}
