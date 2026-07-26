<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\Wishlist;
use App\Services\ProductAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function __construct(private ProductAnalyticsService $analytics) {}

    public function index(Request $request): JsonResponse
    {
        $items = Wishlist::with(['product.images', 'product.seller.sellerProfile', 'product.category'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => $items->map(fn (Wishlist $item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product' => $item->product ? new ProductResource($item->product) : null,
            ])->values(),
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        Product::where('id', $validated['product_id'])
            ->visibleInShop()
            ->firstOrFail();

        $existing = Wishlist::withTrashed()
            ->where('user_id', $request->user()->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        $product = Product::find($validated['product_id']);
        $wishlisted = false;

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
                $wishlisted = true;
                if ($product) {
                    $this->analytics->recordWishlistAdd($product);
                }
            } else {
                $existing->delete();
                $wishlisted = false;
                if ($product) {
                    $this->analytics->recordWishlistRemove($product);
                }
            }
        } else {
            Wishlist::create([
                'user_id' => $request->user()->id,
                'product_id' => $validated['product_id'],
            ]);
            $wishlisted = true;
            if ($product) {
                $this->analytics->recordWishlistAdd($product);
            }
        }

        return response()->json([
            'wishlisted' => $wishlisted,
            'message' => $wishlisted ? 'Added to wishlist.' : 'Removed from wishlist.',
        ]);
    }

    public function destroy(Request $request, Wishlist $wishlist): JsonResponse
    {
        abort_unless($wishlist->user_id === $request->user()->id, 403);

        $product = $wishlist->product;
        $wishlist->delete();
        if ($product) {
            $this->analytics->recordWishlistRemove($product);
        }

        return $this->index($request);
    }
}
