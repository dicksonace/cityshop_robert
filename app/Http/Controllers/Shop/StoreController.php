<?php

namespace App\Http\Controllers\Shop;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $store = SellerProfile::with('user')
            ->where('slug', $slug)
            ->where('status', SellerStatus::Approved)
            ->firstOrFail();

        $products = Product::with(['images', 'category'])
            ->visibleInShop()
            ->where('seller_id', $store->user_id)
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $productCount = Product::visibleInShop()
            ->where('seller_id', $store->user_id)
            ->count();

        $sellerReviewCount = Review::query()
            ->whereHas('product', fn ($q) => $q->where('seller_id', $store->user_id))
            ->count();

        return Inertia::render('shop/store', [
            'store' => $store,
            'products' => $products,
            'productCount' => $productCount,
            'storeUrl' => route('store.show', $store->slug, absolute: true),
            'sellerReviewCount' => $sellerReviewCount,
        ]);
    }
}
