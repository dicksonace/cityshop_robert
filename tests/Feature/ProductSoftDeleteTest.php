<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_delete_sets_deleted_at(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'price' => 100,
            'quantity' => 5,
            'status' => ProductStatus::Approved,
        ]);

        $product->delete();

        $this->assertSoftDeleted($product);
        $this->assertNull(Product::find($product->id));
        $this->assertNotNull(Product::withTrashed()->find($product->id));
    }

    public function test_soft_deleted_products_are_excluded_from_visible_in_shop_scope(): void
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Hidden Product',
            'slug' => 'hidden-product',
            'price' => 50,
            'quantity' => 1,
            'status' => ProductStatus::Approved,
        ]);

        $product->delete();

        $this->assertFalse(Product::visibleInShop()->whereKey($product->id)->exists());
    }
}
