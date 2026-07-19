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
}
