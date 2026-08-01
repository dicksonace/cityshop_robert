<?php

namespace Tests\Feature\Api;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Links made before the app carried slugs point at a product by id, so the
 * lookup answers to both instead of dead-ending on a Page Not Found.
 */
class ApiProductLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_product_can_be_opened_by_slug(): void
    {
        $product = $this->product();

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_a_product_can_be_opened_by_id(): void
    {
        $product = $this->product();

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.slug', $product->slug);
    }

    public function test_an_unknown_id_is_still_a_miss(): void
    {
        $this->product();

        $this->getJson('/api/v1/products/999999')->assertNotFound();
    }

    public function test_a_hidden_product_stays_hidden_by_id(): void
    {
        $product = $this->product(ProductStatus::Pending);

        $this->getJson("/api/v1/products/{$product->id}")->assertNotFound();
    }

    private function product(ProductStatus $status = ProductStatus::Approved): Product
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'City Unlock',
            'slug' => 'city-unlock-'.uniqid(),
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);

        return Product::create([
            'seller_id' => $seller->id,
            'name' => 'Honda',
            'slug' => 'honda-'.uniqid(),
            'price' => 45000,
            'quantity' => 1,
            'status' => $status,
            'is_preorder' => false,
        ]);
    }
}
