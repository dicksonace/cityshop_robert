<?php

namespace App\Http\Controllers\Shop;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use App\Services\ProductDiscoveryService;
use App\Services\StoreCustomizationService;
use App\Support\InfiniteScroll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function __construct(private ProductDiscoveryService $discovery) {}

    public function show(Request $request, string $slug, StoreCustomizationService $customizations): Response|JsonResponse
    {
        $store = SellerProfile::with(['user', 'storeCustomization'])
            ->where('slug', $slug)
            ->where('status', SellerStatus::Approved)
            ->firstOrFail();

        $customization = $customizations->forProfile($store);
        $settings = $customizations->publishedSettings($customization);
        $sections = $customizations->orderedSections($settings);

        $search = trim((string) $request->get('search', $request->get('q', '')));
        $categoryId = $request->integer('category') ?: null;

        $baseQuery = Product::with(['images', 'category'])
            ->visibleInShop()
            ->where('seller_id', $store->user_id);

        $productQuery = clone $baseQuery;

        if ($search !== '') {
            $this->discovery->applySearch($productQuery, $search);
        }

        if ($categoryId) {
            $productQuery->where('category_id', $categoryId);
        }

        if ($search !== '') {
            $this->discovery->applySort($productQuery, 'relevance', $this->discovery->resolveRandomSeed($request), $request->user());
        } else {
            $productQuery->latest();
        }

        $products = $productQuery->paginate(12)->withQueryString();

        if (InfiniteScroll::wants($request)) {
            return InfiniteScroll::json($products);
        }

        $productCount = (clone $baseQuery)->count();
        $isSearching = $search !== '' || $categoryId !== null;

        $sellerReviewCount = Review::query()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $store->user_id))
            ->count();

        $sectionProducts = [];
        if (! $isSearching && in_array('featured', $sections, true)) {
            $sectionProducts['featured'] = (clone $baseQuery)->latest()->limit(4)->get();
        }
        if (in_array('products', $sections, true)) {
            $sectionProducts['products'] = $products;
        }

        $onSale = collect();
        if (! $isSearching && (($settings['sections']['enabled']['promo'] ?? false) || in_array('featured', $sections, true))) {
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
            'search' => $search,
        ]);
    }
}
