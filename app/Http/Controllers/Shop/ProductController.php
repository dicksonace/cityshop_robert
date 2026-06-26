<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Review;
use App\Services\ProductAnalyticsService;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(private ProductAnalyticsService $analytics) {}

    public function show(Request $request, string $slug): Response
    {
        $product = Product::with(['images', 'seller.sellerProfile', 'category'])
            ->visibleInShop()
            ->where('slug', $slug)
            ->firstOrFail();

        $this->analytics->recordView($product);

        $reviews = Review::with('user')
            ->where('product_id', $product->id)
            ->latest()
            ->paginate(10, ['*'], 'reviews_page')
            ->withQueryString();

        $reviewable = $request->user()
            ? ReviewService::findReviewableItem($request->user(), $product)
            : null;

        $related = Product::with('images')
            ->visibleInShop()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get();

        return Inertia::render('shop/product-show', [
            'product' => $product,
            'related' => $related,
            'reviews' => $reviews,
            'reviewable' => $reviewable ? [
                'order_id' => $reviewable->order_id,
                'order_item_id' => $reviewable->id,
            ] : null,
        ]);
    }
}
