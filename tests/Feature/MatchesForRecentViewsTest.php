<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchesForRecentViewsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Category, 1: Product, 2: Product}
     */
    private function seedCategoryWithProducts(): array
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Match Store',
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

        $category = Category::create([
            'name' => 'Recent Match Cat',
            'slug' => 'recent-match-cat-'.uniqid(),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $viewed = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Viewed Phone',
            'slug' => 'viewed-phone',
            'price' => 100,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
            'is_preorder' => false,
        ]);

        $match = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Matching Phone',
            'slug' => 'matching-phone',
            'price' => 80,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
            'is_preorder' => false,
        ]);

        return [$category, $viewed, $match];
    }

    public function test_returns_category_matches_excluding_viewed_products(): void
    {
        [, $viewed, $match] = $this->seedCategoryWithProducts();

        $response = $this->getJson(route('home.matches-for-recent-views', [
            'ids' => (string) $viewed->id,
        ]));

        $response->assertOk()
            ->assertJsonPath('products.0.id', $match->id)
            ->assertJsonStructure([
                'products' => [
                    ['id', 'name', 'slug', 'price', 'discount_price', 'images', 'category_id', 'sellers_in_category'],
                ],
            ]);

        $ids = collect($response->json('products'))->pluck('id');
        $this->assertFalse($ids->contains($viewed->id));
        $this->assertTrue($ids->contains($match->id));
    }

    public function test_returns_empty_when_no_ids_provided(): void
    {
        $this->seedCategoryWithProducts();

        $this->getJson(route('home.matches-for-recent-views'))
            ->assertOk()
            ->assertJson(['products' => []]);
    }
}
