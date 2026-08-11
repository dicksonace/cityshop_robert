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
use App\Models\ProductImage;
use App\Models\SellerProfile;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SeoAndPaymentHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_includes_home_and_visible_product(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'SEO Store',
            'slug' => 'seo-store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'SEO Phone',
            'slug' => 'seo-phone-'.uniqid(),
            'price' => 99,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
        ]);

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('/products/'.$product->slug, false);
        $response->assertSee('/store/seo-store', false);
    }

    public function test_product_page_html_includes_item_image_for_chat_link_previews(): void
    {
        config(['app.url' => 'https://cityunlock.net']);

        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Share Store',
            'slug' => 'share-store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Net Power Switch',
            'slug' => 'net-power-switch',
            'description' => 'Wall switch for home and office.',
            'price' => 45,
            'quantity' => 8,
            'status' => ProductStatus::Approved,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/net-power-switch.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $html = $this->get('/products/net-power-switch')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('property="og:title"', $html);
        $this->assertStringContainsString('Net Power Switch', $html);
        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('products/net-power-switch.jpg', $html);
        $this->assertStringContainsString('property="og:type" content="product"', $html);
        $this->assertStringContainsString('twitter:card" content="summary_large_image"', $html);
    }

    public function test_robots_txt_disallows_private_areas_and_points_to_sitemap(): void
    {
        config(['app.url' => 'https://cityunlock.net']);

        $response = $this->get(route('robots'));

        $response->assertOk();
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Disallow: /checkout', false);
        $response->assertSee('Sitemap: https://cityunlock.net/sitemap.xml', false);
    }

    public function test_paystack_webhook_rejects_invalid_signature(): void
    {
        config([
            'services.paystack.secret_key' => 'sk_test_secret',
            'services.paystack.webhook_secret' => 'sk_test_secret',
        ]);

        $this->postJson('/webhooks/paystack', [
            'event' => 'charge.success',
            'data' => ['reference' => 'x'],
        ], [
            'x-paystack-signature' => 'invalid',
        ])->assertStatus(400);
    }

    public function test_fulfill_paid_checkout_is_idempotent_under_lock(): void
    {
        Notification::fake();

        [$checkout] = $this->makePendingMarketplaceCheckout(total: 100);

        $service = app(OrderService::class);
        $service->fulfillPaidCheckout($checkout, 'ref-idempotent-1', 100.0);
        $service->fulfillPaidCheckout($checkout->fresh(), 'ref-idempotent-1', 100.0);

        $this->assertSame(PaymentStatus::Paid, $checkout->fresh()->payment_status);
        $this->assertSame(1, Order::where('checkout_id', $checkout->id)->where('payment_status', PaymentStatus::Paid)->count());
    }

    public function test_fulfill_paid_checkout_rejects_amount_mismatch(): void
    {
        Notification::fake();

        [$checkout] = $this->makePendingMarketplaceCheckout(total: 100);

        try {
            app(OrderService::class)->fulfillPaidCheckout($checkout, 'ref-mismatch', 50.0);
            $this->fail('Expected amount mismatch exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('Payment amount does not match checkout total.', $e->getMessage());
        }

        $this->assertSame(PaymentStatus::Pending, $checkout->fresh()->payment_status);
    }

    public function test_paystack_webhook_does_not_fulfill_on_amount_mismatch(): void
    {
        Notification::fake();

        config([
            'services.paystack.secret_key' => 'sk_test_secret',
            'services.paystack.webhook_secret' => 'sk_test_secret',
        ]);

        [$checkout] = $this->makePendingMarketplaceCheckout(total: 100);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'reference' => 'ref-wh-mismatch',
                'amount' => 5000,
                'metadata' => [
                    'checkout_id' => $checkout->id,
                    'expected_amount' => 100,
                ],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, 'sk_test_secret');

        $this->call(
            'POST',
            '/webhooks/paystack',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ],
            $payload,
        )->assertOk();

        $this->assertSame(PaymentStatus::Pending, $checkout->fresh()->payment_status);
    }

    public function test_paystack_webhook_logs_charge_failed_without_error(): void
    {
        config([
            'services.paystack.secret_key' => 'sk_test_secret',
            'services.paystack.webhook_secret' => 'sk_test_secret',
        ]);

        $payload = json_encode([
            'event' => 'charge.failed',
            'data' => [
                'reference' => 'ref-failed',
                'gateway_response' => 'Insufficient funds',
            ],
        ]);
        $signature = hash_hmac('sha512', $payload, 'sk_test_secret');

        $this->call(
            'POST',
            '/webhooks/paystack',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            ],
            $payload,
        )->assertOk();
    }

    /**
     * @return array{0: Checkout, 1: Order}
     */
    private function makePendingMarketplaceCheckout(float $total): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Pay Store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Pay Item',
            'slug' => 'pay-item-'.uniqid(),
            'price' => $total,
            'quantity' => 5,
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
            'subtotal' => $total,
            'shipping_cost' => 0,
            'total' => $total,
        ]);

        $order = Order::create([
            'checkout_id' => $checkout->id,
            'order_number' => Order::generateOrderNumber(),
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'status' => OrderStatus::Pending,
            'payment_status' => PaymentStatus::Pending,
            'payment_channel' => PaymentChannel::Marketplace,
            'payment_method' => 'momo',
            'receiver_name' => 'Buyer',
            'receiver_phone' => '0240000000',
            'region' => 'Greater Accra',
            'city' => 'Accra',
            'subtotal' => $total,
            'shipping_cost' => 0,
            'commission_amount' => round($total * 0.05, 2),
            'total' => $total,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => 'Pay Item',
            'quantity' => 1,
            'unit_price' => $total,
            'commission_rate' => 5,
            'commission_amount' => round($total * 0.05, 2),
            'seller_amount' => round($total * 0.95, 2),
            'status' => OrderStatus::Pending,
        ]);

        return [$checkout->fresh('orders.items'), $order];
    }
}
