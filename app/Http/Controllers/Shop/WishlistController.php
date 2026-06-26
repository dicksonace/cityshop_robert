<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use App\Services\ProductAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WishlistController extends Controller
{
    public function __construct(private ProductAnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        $items = Wishlist::with(['product.images', 'product.seller.sellerProfile', 'product.category'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return Inertia::render('shop/wishlist', [
            'items' => $items,
        ]);
    }

    public function toggle(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        Product::where('id', $validated['product_id'])
            ->visibleInShop()
            ->firstOrFail();

        $existing = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', 'Removed from wishlist.');
        }

        Wishlist::create([
            'user_id' => $request->user()->id,
            'product_id' => $validated['product_id'],
        ]);

        $product = Product::find($validated['product_id']);
        if ($product) {
            $this->analytics->recordWishlistAdd($product);
        }

        return back()->with('success', 'Added to wishlist!');
    }

    public function destroy(Request $request, Wishlist $wishlist): RedirectResponse
    {
        abort_unless($wishlist->user_id === $request->user()->id, 403);

        $wishlist->delete();

        return back()->with('success', 'Removed from wishlist.');
    }
}
