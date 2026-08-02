<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SellerCashOnDeliverySwitchTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private SellerProfile $profile;

    private User $buyer;

    private Product $product;

    private BuyerAddress $address;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->seller = User::factory()->create(['role' => UserRole::Seller]);
        $this->profile = SellerProfile::create([
            'user_id' => $this->seller->id,
            'store_name' => 'Cash Corner',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'accept_marketplace_payments' => true,
        ]);

        StoreCustomization::create([
            'seller_profile_id' => $this->profile->id,
            'setup_completed_at' => now(),
            'published_at' => now(),
            'published_settings' => [],
            'draft_settings' => [],
        ]);

        $this->product = Product::create([
            'seller_id' => $this->seller->id,
            'name' => 'Kettle',
            'slug' => 'kettle',
            'price' => 120,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
            'cash_on_delivery' => true,
        ]);

        $this->buyer = User::factory()->create(['role' => UserRole::Buyer]);

        CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $this->address = BuyerAddress::create([
            'user_id' => $this->buyer->id,
            'first_name' => 'Kofi',
            'last_name' => 'Amoah',
            'phone' => '0538790083',
            'address_line' => 'Sefwi Bekwai',
            'region' => 'Western North',
            'city' => 'Sefwi Bekwai',
            'is_default' => true,
        ]);
    }

    public function test_stores_take_cash_on_delivery_until_they_turn_it_off(): void
    {
        $this->assertTrue($this->profile->acceptsCashOnDelivery());

        $this->actingAs($this->seller)
            ->post(route('seller.payment-methods.settings'), [
                'accept_marketplace_payments' => true,
                'accept_direct_payments' => false,
                'cash_on_delivery_enabled' => false,
            ])
            ->assertRedirect();

        $this->assertFalse($this->profile->fresh()->acceptsCashOnDelivery());

        // And back on again, any time.
        $this->actingAs($this->seller)
            ->post(route('seller.payment-methods.settings'), [
                'accept_marketplace_payments' => true,
                'accept_direct_payments' => false,
                'cash_on_delivery_enabled' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($this->profile->fresh()->acceptsCashOnDelivery());
    }

    public function test_checkout_tells_both_clients_the_store_stopped_taking_cash(): void
    {
        $this->profile->update(['cash_on_delivery_enabled' => false]);

        $this->actingAs($this->buyer)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/checkout')
                ->where('sellerGroups.0.accepts_cash', false)
            );

        Sanctum::actingAs($this->buyer);

        $this->getJson('/api/v1/checkout')
            ->assertOk()
            ->assertJsonPath('seller_groups.0.accepts_cash', false);
    }

    public function test_a_cash_order_is_refused_for_a_store_that_switched_it_off(): void
    {
        $this->profile->update(['cash_on_delivery_enabled' => false]);

        $this->actingAs($this->buyer)
            ->post(route('checkout.store'), [
                'address_id' => $this->address->id,
                'payment_method' => 'cash',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Cash Corner'));

        $this->assertSame(0, Order::count());
        $this->assertDatabaseHas('cart_items', ['user_id' => $this->buyer->id]);

        Sanctum::actingAs($this->buyer);

        $this->postJson('/api/v1/checkout', [
            'address_id' => $this->address->id,
            'payment_method' => 'cash',
        ])->assertStatus(422);

        $this->assertSame(0, Order::count());
    }

    public function test_a_cash_order_goes_through_while_the_store_allows_it(): void
    {
        $this->actingAs($this->buyer)
            ->post(route('checkout.store'), [
                'address_id' => $this->address->id,
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $this->assertSame(1, Order::count());
        $this->assertSame('cash', Order::first()->payment_method);
    }

    public function test_the_product_page_only_advertises_cash_the_store_still_takes(): void
    {
        $this->actingAs($this->buyer)
            ->get(route('products.show', $this->product->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('cashOnDelivery', true));

        $this->profile->update(['cash_on_delivery_enabled' => false]);

        $this->actingAs($this->buyer)
            ->get(route('products.show', $this->product->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('cashOnDelivery', false));
    }
}
