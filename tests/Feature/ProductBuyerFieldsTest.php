<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SellerProfile;
use App\Models\StoreCustomization;
use App\Models\User;
use App\Services\ProductBuyerFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductBuyerFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seller_can_save_dynamic_buyer_fields_on_a_product(): void
    {
        [$seller, $product] = $this->approvedSellerWithProduct();

        Sanctum::actingAs($seller);

        $this->patchJson('/api/v1/seller/products/'.$product->id, [
                'buyer_fields' => [
                    ['label' => 'IMEI', 'placeholder' => '15-digit IMEI', 'type' => 'text', 'required' => true],
                    ['label' => 'Ghana Card', 'placeholder' => 'GHA-000', 'type' => 'text', 'required' => false],
                    ['label' => '', 'placeholder' => 'skip me'],
                ],
            ])
            ->assertOk();

        $product->refresh();
        $this->assertCount(2, $product->buyer_fields);
        $this->assertSame('IMEI', $product->buyer_fields[0]['label']);
        $this->assertSame('imei', $product->buyer_fields[0]['key']);
    }

    public function test_cart_requires_buyer_fields_when_the_seller_added_them(): void
    {
        [, $product] = $this->approvedSellerWithProduct();
        $product->update([
            'buyer_fields' => ProductBuyerFieldService::normalize([
                ['label' => 'Alipay ID', 'placeholder' => 'ID', 'required' => true],
            ]),
        ]);

        $buyer = User::factory()->create(['role' => UserRole::Buyer]);
        Sanctum::actingAs($buyer);

        $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(422);

        $this->postJson('/api/v1/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
            'buyer_field_values' => ['alipay_id' => 'ali-123'],
        ])->assertCreated();
    }

    /**
     * @return array{0: User, 1: Product}
     */
    private function approvedSellerWithProduct(): array
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);
        $profile = SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'Field Store',
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

        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'FRP unlock',
            'slug' => 'frp-unlock-'.uniqid(),
            'price' => 80,
            'quantity' => 10,
            'status' => ProductStatus::Approved,
            'free_shipping' => false,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/frp.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        return [$seller, $product];
    }
}
