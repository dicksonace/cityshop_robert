<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerPaymentMethodType;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiSellerHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyers_cannot_open_the_seller_hub(): void
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($buyer);

        $this->getJson('/api/v1/seller/dashboard')->assertForbidden();
        $this->getJson('/api/v1/seller/orders')->assertForbidden();
        $this->getJson('/api/v1/seller/products')->assertForbidden();
    }

    public function test_seller_can_list_and_advance_an_order(): void
    {
        [$seller, $item] = $this->paidOrder(OrderStatus::Pending);

        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/seller/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('counts.new', 1)
            ->assertJsonPath('data.0.status', 'pending');

        $this->getJson('/api/v1/seller/orders?stage=new')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson("/api/v1/seller/orders/{$item->id}", ['status' => 'processing'])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.next_actions.0.status', 'packed');

        $this->assertSame(OrderStatus::Processing, $item->fresh()->status);
    }

    public function test_seller_can_cancel_a_pending_order(): void
    {
        [$seller, $item] = $this->paidOrder(OrderStatus::Pending);
        Sanctum::actingAs($seller);

        $this->postJson("/api/v1/seller/orders/{$item->id}/reject", [
            'cancellation_code' => 'out_of_stock',
        ])->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_seller_can_list_hide_and_create_products(): void
    {
        Storage::fake('public');
        $seller = $this->approvedSeller();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Live lamp',
            'slug' => 'live-lamp-'.uniqid(),
            'price' => 40,
            'quantity' => 2,
            'status' => ProductStatus::Approved,
        ]);

        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/seller/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.is_live', true)
            ->assertJsonPath('can_create', true);

        $this->postJson("/api/v1/seller/products/{$product->id}/visibility")
            ->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.is_live', false);

        $this->post('/api/v1/seller/products', [
            'name' => 'Desk fan',
            'price' => 85,
            'quantity' => 4,
            'images' => [UploadedFile::fake()->image('fan.jpg')],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Desk fan')
            ->assertJsonPath('data.is_live', true);

        $this->assertDatabaseHas('products', [
            'seller_id' => $seller->id,
            'name' => 'Desk fan',
            'status' => ProductStatus::Approved->value,
        ]);
    }

    public function test_seller_can_duplicate_a_product_and_open_analytics(): void
    {
        $seller = $this->approvedSeller();
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Speaker',
            'slug' => 'speaker-'.uniqid(),
            'price' => 50,
            'quantity' => 3,
            'status' => ProductStatus::Approved,
        ]);

        Sanctum::actingAs($seller);

        $this->postJson("/api/v1/seller/products/{$product->id}/duplicate")
            ->assertCreated()
            ->assertJsonPath('data.name', 'Speaker (Copy)')
            ->assertJsonPath('data.status', 'draft');

        $this->getJson("/api/v1/seller/products/{$product->id}/analytics")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonStructure(['stats' => ['views', 'purchases', 'revenue']]);
    }

    public function test_seller_can_manage_coupons_reviews_followers_and_store(): void
    {
        $seller = $this->approvedSeller();
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Lamp',
            'slug' => 'lamp-'.uniqid(),
            'price' => 20,
            'quantity' => 2,
            'status' => ProductStatus::Approved,
        ]);
        $order = Order::create([
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
            'subtotal' => 20,
            'shipping_cost' => 0,
            'total' => 20,
        ]);
        $review = \App\Models\Review::create([
            'product_id' => $product->id,
            'user_id' => $buyer->id,
            'order_id' => $order->id,
            'rating' => 5,
            'comment' => 'Works well',
        ]);
        \App\Models\SellerFollow::create([
            'follower_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);

        Sanctum::actingAs($seller);

        $this->postJson('/api/v1/seller/promotions', [
            'code' => 'SAVE10',
            'type' => 'percentage',
            'value' => 10,
        ])->assertCreated()
            ->assertJsonPath('data.code', 'SAVE10');

        $this->getJson('/api/v1/seller/reviews')
            ->assertOk()
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('data.0.id', $review->id);

        $this->postJson("/api/v1/seller/reviews/{$review->id}/reply", [
            'seller_reply' => 'Thank you!',
        ])->assertOk()
            ->assertJsonPath('data.seller_reply', 'Thank you!');

        $this->getJson('/api/v1/seller/followers')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->getJson('/api/v1/seller/refunds')
            ->assertOk()
            ->assertJsonPath('counts.all', 0);

        $this->getJson('/api/v1/seller/store')
            ->assertOk()
            ->assertJsonPath('store_name', 'Hub Store');

        $this->patchJson('/api/v1/seller/store', [
            'slogan' => 'Quality first',
            'preset' => 'forest',
        ])->assertOk()
            ->assertJsonPath('slogan', 'Quality first')
            ->assertJsonPath('preset', 'forest');
    }

    public function test_seller_can_download_an_order_pdf(): void
    {
        [$seller, $item] = $this->paidOrder(OrderStatus::Pending);
        Sanctum::actingAs($seller);

        $this->get("/api/v1/seller/orders/{$item->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_seller_can_add_a_momo_payment_method(): void
    {
        $seller = $this->approvedSeller();
        Sanctum::actingAs($seller);

        $this->getJson('/api/v1/seller/payment-methods')
            ->assertOk()
            ->assertJsonPath('profile.accept_marketplace_payments', true);

        $this->postJson('/api/v1/seller/payment-methods', [
            'type' => SellerPaymentMethodType::MobileMoney->value,
            'account_name' => 'Ama Seller',
            'account_number' => '0240000000',
            'network' => 'MTN',
            'is_default' => true,
        ])->assertCreated()
            ->assertJsonPath('methods.0.account_number', '0240000000');
    }

    public function test_seller_can_save_full_listing_bulk_hide_and_store_designer(): void
    {
        Storage::fake('public');
        $seller = $this->approvedSeller();
        $category = \App\Models\Category::query()->where('slug', 'phones-tablets')->first()
            ?? \App\Models\Category::query()->first()
            ?? \App\Models\Category::create([
                'name' => 'Phones',
                'slug' => 'phones-tablets-hub-'.uniqid(),
                'is_active' => true,
            ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Old phone',
            'slug' => 'old-phone-'.uniqid(),
            'price' => 40,
            'quantity' => 2,
            'status' => ProductStatus::Approved,
        ]);

        Sanctum::actingAs($seller);

        $this->post('/api/v1/seller/products', [
            'name' => 'Pixel phone',
            'price' => 900,
            'quantity' => 3,
            'category_id' => $category->id,
            'brand' => 'Google',
            'condition' => 'new',
            'sku' => 'PIX-1',
            'shipping_type' => 'paid',
            'delivery_fee' => 15,
            'delivery_days' => 3,
            'pickup_available' => 1,
            'ships_nationwide' => 1,
            'specifications' => ['storage' => '128GB', 'ram' => '8GB'],
            'images' => [UploadedFile::fake()->image('phone.jpg')],
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.brand', 'Google')
            ->assertJsonPath('data.shipping_type', 'paid');

        $this->postJson('/api/v1/seller/products/bulk', [
            'action' => 'hide',
            'product_ids' => [$product->id],
        ])->assertOk()
            ->assertJsonPath('count', 1);
        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);

        $this->patchJson('/api/v1/seller/store', [
            'announcement_enabled' => true,
            'announcement_text' => 'Free delivery this week',
            'promo_enabled' => true,
            'promo_text' => '10% off',
            'sections_enabled' => ['featured' => true, 'about' => false],
        ])->assertOk()
            ->assertJsonPath('announcement.enabled', true)
            ->assertJsonPath('announcement.text', 'Free delivery this week')
            ->assertJsonPath('sections_enabled.about', false);

        $this->post('/api/v1/seller/store/hero', [
            'hero_images' => [UploadedFile::fake()->image('hero.jpg')],
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonCount(1, 'hero_images');

        $this->patchJson('/api/v1/seller/account/order-sms', [
            'order_sms_mobile_1' => '0240000000',
            'order_sms_mobile_2' => '0241111111',
        ])->assertOk()
            ->assertJsonPath('order_sms_mobile_1', '0240000000');
    }

    public function test_seller_can_pay_activation_from_wallet(): void
    {
        $seller = $this->approvedSeller();
        $seller->sellerProfile->update([
            'activation_fee_amount' => 50,
            'activation_prompted_at' => now(),
        ]);
        \App\Services\PaymentPinService::set($seller, '5826');
        $wallet = \App\Services\WalletService::ensure($seller);
        $wallet->update(['available_balance' => 80]);

        Sanctum::actingAs($seller->fresh());

        $this->getJson('/api/v1/seller/account')
            ->assertOk()
            ->assertJsonPath('activation.needs_payment', true)
            ->assertJsonPath('has_payment_pin', true);

        $this->postJson('/api/v1/seller/activation/pay', [
            'payment_pin' => '5826',
        ])->assertOk()
            ->assertJsonPath('activation.needs_payment', false)
            ->assertJsonPath('message', 'Service fee paid. Your store is active for 1 year.');
    }

    /**
     * @return array{0: User, 1: OrderItem}
     */
    private function paidOrder(OrderStatus $status): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = $this->approvedSeller();

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
            'payment_status' => PaymentStatus::Paid,
            'payment_channel' => PaymentChannel::Marketplace,
            'payment_method' => 'momo',
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

    private function approvedSeller(): User
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Hub Store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'accept_marketplace_payments' => true,
            'accept_direct_payments' => false,
        ]);

        return $seller->fresh();
    }
}
