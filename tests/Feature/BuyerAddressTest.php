<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\BuyerAddress;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuyerAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function buyer(): User
    {
        return User::factory()->create(['role' => UserRole::Buyer, 'mobile' => '0240000000']);
    }

    private function approvedSellerWithProduct(): array
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Addr Store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
            'accept_marketplace_payments' => true,
            'accept_direct_payments' => false,
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
            'name' => 'Test item',
            'slug' => 'test-'.uniqid(),
            'price' => 50,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
        ]);

        return [$seller, $product];
    }

    public function test_buyer_can_save_and_list_addresses(): void
    {
        $buyer = $this->buyer();

        $this->actingAs($buyer)
            ->post(route('addresses.store'), [
                'first_name' => 'Robert',
                'last_name' => 'Asare',
                'phone' => '0248520718',
                'secondary_phone' => '0248520718',
                'address_line' => 'Near Sefwi Bekwai station',
                'region' => 'Western North',
                'city' => 'Sefwi Bekwai',
                'is_default' => true,
            ])
            ->assertRedirect(route('addresses.index'));

        $this->assertDatabaseHas('buyer_addresses', [
            'user_id' => $buyer->id,
            'city' => 'Sefwi Bekwai',
            'is_default' => 1,
        ]);

        $this->actingAs($buyer)
            ->get(route('addresses.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/addresses/index')
                ->has('addresses', 1));
    }

    public function test_buyer_can_delete_address(): void
    {
        $buyer = $this->buyer();
        $address = BuyerAddress::create([
            'user_id' => $buyer->id,
            'first_name' => 'Robert',
            'last_name' => 'Asare',
            'phone' => '0248520718',
            'address_line' => 'Home',
            'region' => 'Western',
            'city' => 'Takoradi',
            'is_default' => true,
        ]);

        $this->actingAs($buyer)
            ->delete(route('addresses.destroy', $address))
            ->assertRedirect();

        $this->assertSoftDeleted('buyer_addresses', ['id' => $address->id]);
    }

    public function test_checkout_uses_selected_saved_address(): void
    {
        $buyer = $this->buyer();
        [, $product] = $this->approvedSellerWithProduct();

        CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $address = BuyerAddress::create([
            'user_id' => $buyer->id,
            'first_name' => 'Robert',
            'last_name' => 'Asare',
            'phone' => '0248520718',
            'address_line' => 'Near station',
            'additional_details' => 'Gate blue',
            'region' => 'Western North',
            'city' => 'Sefwi Bekwai',
            'is_default' => true,
        ]);

        $this->actingAs($buyer)
            ->post(route('checkout.store'), [
                'address_id' => $address->id,
                'payment_method' => 'cash',
                'seller_payments' => [],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'buyer_id' => $buyer->id,
            'receiver_name' => 'Robert Asare',
            'receiver_phone' => '0248520718',
            'region' => 'Western North',
            'city' => 'Sefwi Bekwai',
        ]);
    }
}
