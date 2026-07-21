<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartQuantityTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(): array
    {
        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Cart Store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Cart Test Phone',
            'slug' => 'cart-test-phone-'.uniqid(),
            'price' => 15,
            'quantity' => 10,
            'status' => ProductStatus::Approved,
            'free_shipping' => true,
            'is_preorder' => false,
        ]);

        return [$buyer, $product];
    }

    public function test_first_add_starts_at_quantity_one(): void
    {
        [$buyer, $product] = $this->makeProduct();

        $this->actingAs($buyer)
            ->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'deleted_at' => null,
        ]);
    }

    public function test_re_adding_after_remove_resets_to_one_not_two(): void
    {
        [$buyer, $product] = $this->makeProduct();

        $item = CartItem::create([
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $item->delete();

        $this->actingAs($buyer)
            ->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'deleted_at' => null,
        ]);
        $this->assertSame(1, CartItem::where('user_id', $buyer->id)->where('product_id', $product->id)->count());
    }

    public function test_second_add_increments_existing_active_item(): void
    {
        [$buyer, $product] = $this->makeProduct();

        $this->actingAs($buyer)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);
        $this->actingAs($buyer)->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 1]);

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $buyer->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }
}
