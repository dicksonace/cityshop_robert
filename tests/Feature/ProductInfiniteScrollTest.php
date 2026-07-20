<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductInfiniteScrollTest extends TestCase
{
    use RefreshDatabase;

    private function seedProducts(int $count): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Feed Store',
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

        for ($i = 1; $i <= $count; $i++) {
            Product::create([
                'seller_id' => $seller->id,
                'name' => "Feed Product {$i}",
                'slug' => "feed-product-{$i}",
                'price' => 10 + $i,
                'quantity' => 5,
                'status' => ProductStatus::Approved,
                'free_shipping' => true,
                'is_preorder' => false,
            ]);
        }
    }

    public function test_home_returns_json_page_for_infinite_scroll(): void
    {
        $this->seedProducts(25);

        $response = $this->get(route('home', ['page' => 2, 'sort' => 'newest']), [
            'X-Infinite-Scroll' => '1',
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
                'has_more',
                'next_page',
            ]);

        $this->assertSame(2, $response->json('current_page'));
        $this->assertSame(20, $response->json('per_page'));
        $this->assertCount(5, $response->json('data'));
        $this->assertFalse($response->json('has_more'));
    }

    public function test_home_still_renders_inertia_without_infinite_header(): void
    {
        $this->seedProducts(3);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('shop/home')->has('products.data', 3));
    }

    public function test_store_returns_json_page_for_infinite_scroll(): void
    {
        $this->seedProducts(15);
        $store = SellerProfile::query()->firstOrFail();

        $response = $this->get(route('store.show', ['slug' => $store->slug, 'page' => 2]), [
            'X-Infinite-Scroll' => '1',
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
                'has_more',
                'next_page',
            ]);

        $this->assertSame(2, $response->json('current_page'));
        $this->assertSame(12, $response->json('per_page'));
        $this->assertCount(3, $response->json('data'));
        $this->assertFalse($response->json('has_more'));
    }

    public function test_store_still_renders_inertia_without_infinite_header(): void
    {
        $this->seedProducts(3);
        $store = SellerProfile::query()->firstOrFail();

        $this->get(route('store.show', $store->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('shop/store')->has('products.data', 3));
    }

    public function test_store_search_filters_products_within_store(): void
    {
        $this->seedProducts(3);
        $store = SellerProfile::query()->firstOrFail();
        $seller = User::query()->findOrFail($store->user_id);

        Product::create([
            'seller_id' => $seller->id,
            'name' => 'Unique Blue Bicycle',
            'slug' => 'unique-blue-bicycle',
            'price' => 99,
            'quantity' => 2,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
            'is_preorder' => false,
        ]);

        $otherSeller = User::factory()->create(['role' => UserRole::Seller]);
        $otherProfile = SellerProfile::create([
            'user_id' => $otherSeller->id,
            'store_name' => 'Other Store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);
        StoreCustomization::create([
            'seller_profile_id' => $otherProfile->id,
            'setup_completed_at' => now(),
            'published_at' => now(),
            'published_settings' => [],
            'draft_settings' => [],
        ]);
        Product::create([
            'seller_id' => $otherSeller->id,
            'name' => 'Unique Blue Bicycle Elsewhere',
            'slug' => 'unique-blue-bicycle-elsewhere',
            'price' => 50,
            'quantity' => 1,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
            'is_preorder' => false,
        ]);

        $this->get(route('store.show', ['slug' => $store->slug, 'search' => 'Unique Blue Bicycle']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shop/store')
                ->where('search', 'Unique Blue Bicycle')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Unique Blue Bicycle'));
    }

    public function test_suggest_can_scope_to_seller(): void
    {
        $this->seedProducts(2);
        $store = SellerProfile::query()->firstOrFail();
        $seller = User::query()->findOrFail($store->user_id);

        Product::create([
            'seller_id' => $seller->id,
            'name' => 'Scoped Orange Phone',
            'slug' => 'scoped-orange-phone',
            'price' => 40,
            'quantity' => 3,
            'status' => ProductStatus::Approved,
            'free_shipping' => false,
            'is_preorder' => false,
        ]);

        $otherSeller = User::factory()->create(['role' => UserRole::Seller]);
        Product::create([
            'seller_id' => $otherSeller->id,
            'name' => 'Scoped Orange Phone Other',
            'slug' => 'scoped-orange-phone-other',
            'price' => 40,
            'quantity' => 3,
            'status' => ProductStatus::Approved,
            'free_shipping' => false,
            'is_preorder' => false,
        ]);

        $response = $this->getJson(route('search.suggest', [
            'q' => 'Scoped Orange',
            'seller_id' => $seller->id,
        ]));

        $response->assertOk();
        $names = collect($response->json('products'))->pluck('name')->all();
        $this->assertContains('Scoped Orange Phone', $names);
        $this->assertNotContains('Scoped Orange Phone Other', $names);
    }
}
