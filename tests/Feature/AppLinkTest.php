<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_product_link_opens_the_app_and_keeps_the_preview(): void
    {
        $product = $this->visibleProduct();

        $html = $this->get('/app/products/'.$product->slug)
            ->assertOk()
            ->assertSee('cityshop://products/'.$product->slug, false)
            ->assertSee('/products/'.$product->slug, false)
            ->assertSee('Open in app', false)
            ->assertSee('Continue on the website', false)
            ->getContent();

        $this->assertStringContainsString('og:title', $html);
        $this->assertStringContainsString($product->name, $html);
    }

    public function test_app_store_link_opens_the_app(): void
    {
        $store = $this->approvedStore();

        $this->get('/app/store/'.$store->slug)
            ->assertOk()
            ->assertSee('cityshop://stores/'.$store->slug, false)
            ->assertSee('/store/'.$store->slug, false);
    }

    public function test_app_store_link_preview_uses_the_store_logo(): void
    {
        config(['app.url' => 'https://cityunlock.net']);

        $store = $this->approvedStore();
        $store->update(['shop_photo' => 'sellers/shop-front.jpg']);
        $store->storeCustomization()->create([
            'published_settings' => [
                'branding' => ['store_logo' => 'stores/city-unlock-logo.png'],
            ],
            'draft_settings' => [],
        ]);

        $html = $this->get('/app/store/'.$store->slug)
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('property="og:image"', $html);
        $this->assertStringContainsString('stores/city-unlock-logo.png', $html);
        $this->assertStringNotContainsString('sellers/shop-front.jpg', $html);
    }

    public function test_web_product_url_is_not_the_app_link(): void
    {
        $product = $this->visibleProduct();

        $this->get('/products/'.$product->slug)
            ->assertOk()
            ->assertDontSee('cityshop://products/', false);
    }

    public function test_android_asset_links_are_public(): void
    {
        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonPath('0.target.package_name', 'com.cityshop.cityshop_mobile')
            ->assertJsonPath('0.target.namespace', 'android_app');
    }

    private function visibleProduct(): Product
    {
        $store = $this->approvedStore();
        $product = Product::create([
            'seller_id' => $store->user_id,
            'name' => 'App Link Shirt',
            'slug' => 'app-link-shirt',
            'price' => 210,
            'quantity' => 4,
            'status' => ProductStatus::Approved,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/app-link-shirt.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        return $product;
    }

    private function approvedStore(): SellerProfile
    {
        $seller = User::factory()->create(['role' => UserRole::Seller]);

        return SellerProfile::create([
            'user_id' => $seller->id,
            'store_name' => 'App Link Store',
            'slug' => 'app-link-store',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
