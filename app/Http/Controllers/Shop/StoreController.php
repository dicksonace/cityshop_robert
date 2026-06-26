<?php

namespace App\Http\Controllers\Shop;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Services\StoreCustomizationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function show(Request $request, string $slug, StoreCustomizationService $customizations): Response
    {
        $store = SellerProfile::with(['user', 'storeCustomization'])
            ->where('slug', $slug)
            ->where('status', SellerStatus::Approved)
            ->firstOrFail();

        $customization = $customizations->forProfile($store);
        $settings = $customizations->publishedSettings($customization);
        $sections = $customizations->orderedSections($settings);

        $baseQuery = Product::with(['images', 'category'])
            ->visibleInShop()
            ->where('seller_id', $store->user_id);

        $products = (clone $baseQuery)->latest()->paginate(12)->withQueryString();

        $productCount = (clone $baseQuery)->count();

        $sellerReviewCount = Review::query()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $store->user_id))
            ->count();

        $sectionProducts = [];
        if (in_array('featured', $sections, true)) {
            $sectionProducts['featured'] = (clone $baseQuery)->latest()->limit(4)->get();
        }
        if (in_array('products', $sections, true)) {
            $sectionProducts['products'] = $products;
        }

        $onSale = collect();
        if (($settings['sections']['enabled']['promo'] ?? false) || in_array('featured', $sections, true)) {
            $onSale = (clone $baseQuery)->whereNotNull('discount_price')->latest()->limit(4)->get();
        }

        return Inertia::render('shop/store', [
            'store' => $store,
            'customization' => $settings,
            'sections' => $sections,
            'products' => $products,
            'featuredProducts' => $sectionProducts['featured'] ?? [],
            'onSaleProducts' => $onSale,
            'productCount' => $productCount,
            'storeUrl' => route('store.show', $store->slug, absolute: true),
            'sellerReviewCount' => $sellerReviewCount,
            'promoActive' => $customizations->isPromoActive($settings),
        ]);
    }
}
