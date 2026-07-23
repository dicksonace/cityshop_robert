<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerPaymentMethodType;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerPaymentMethod;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeferredDirectCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_only_continue_does_not_create_order_until_proof_submitted(): void
    {
        Storage::fake('public');
        $this->withoutVite();

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Bank Only Shop',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'accept_direct_payments' => true,
            'accept_marketplace_payments' => false,
        ]);
        $method = SellerPaymentMethod::create([
            'seller_profile_id' => $profile->id,
            'type' => SellerPaymentMethodType::Bank,
            'account_name' => 'Seller Bank',
            'account_number' => '1234567890',
            'bank_name' => 'UBA Bank',
            'is_active' => true,
            'is_default' => true,
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Deferred Bike',
            'slug' => 'deferred-bike',
            'price' => 350,
            'quantity' => 2,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
        ]);

        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $address = BuyerAddress::create([
            'user_id' => $buyer->id,
            'first_name' => 'Kofi',
            'last_name' => 'Amoah',
            'phone' => '0538790083',
            'address_line' => 'Sefwi Bekwai',
            'region' => 'Western North',
            'city' => 'Sefwi Bekwai',
            'is_default' => true,
        ]);

        $this->actingAs($buyer)
            ->post(route('checkout.store'), [
                'address_id' => $address->id,
                'payment_method' => 'momo',
                'seller_payments' => [
                    (string) $seller->id => [
                        'channel' => 'direct',
                        'method_id' => $method->id,
                    ],
                ],
            ])
            ->assertRedirect(route('checkout.direct-pay'));

        $this->assertSame(0, Order::count());
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
        ]);

        $this->actingAs($buyer)
            ->get(route('checkout.direct-pay'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/direct-pay')
                ->has('packages', 1)
            );

        $proof = UploadedFile::fake()->image('paid.jpg');

        $this->actingAs($buyer)
            ->post(route('checkout.direct-pay.submit', $seller->id), [
                'proof' => $proof,
                'reference' => 'TXN123',
            ])
            ->assertRedirect();

        $this->assertSame(1, Order::count());
        $order = Order::first();
        $this->assertSame('direct', $order->payment_channel->value);
        $this->assertSame('TXN123', $order->direct_payment_reference);
        $this->assertNotNull($order->direct_payment_proof_path);
        $this->assertTrue(
            CartItem::where('user_id', $buyer->id)->where('product_id', $product->id)->doesntExist()
        );
    }
}
