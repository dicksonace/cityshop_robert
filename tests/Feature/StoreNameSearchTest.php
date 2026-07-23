<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreNameSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_store_by_name_and_its_products(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'City Unlock',
            'business_name' => 'City Unlock Trading',
            'slug' => 'city-unlock',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Electric Bike',
            'slug' => 'electric-bike-search',
            'price' => 100,
            'quantity' => 3,
            'status' => ProductStatus::Approved,
        ]);

        $otherSeller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $otherSeller->id,
            'store_name' => 'Other Mart',
            'slug' => 'other-mart',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);
        Product::create([
            'seller_id' => $otherSeller->id,
            'name' => 'Random Chair',
            'slug' => 'random-chair-search',
            'price' => 50,
            'quantity' => 2,
            'status' => ProductStatus::Approved,
        ]);

        $page = $this->get(route('search', ['q' => 'City Unlock']))
            ->assertOk();

        $page->assertInertia(fn ($assert) => $assert
            ->component('shop/search')
            ->has('stores', 1)
            ->where('stores.0.slug', 'city-unlock')
            ->where('stores.0.name', 'City Unlock Trading')
            ->has('products.data', 1)
            ->where('products.data.0.id', $product->id)
        );

        $this->getJson(route('search.suggest', ['q' => 'City Unlock']))
            ->assertOk()
            ->assertJsonPath('stores.0.slug', 'city-unlock')
            ->assertJsonPath('products.0.id', $product->id);
    }
}
