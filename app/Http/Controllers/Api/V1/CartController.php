<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\ProductAnalyticsService;
use App\Support\ProductStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    public function __construct(private ProductAnalyticsService $analytics) {}

    public function index(Request $request): JsonResponse
    {
        $items = CartItem::with(['product.images', 'product.seller.sellerProfile', 'product.category'])
            ->where('user_id', $request->user()->id)
            ->get();

        foreach ($items as $item) {
            $product = $item->product;
            if (! $product || $product->is_preorder) {
                continue;
            }

            $available = ProductStock::available($product) ?? 0;

            if ($available < 1) {
                $item->delete();
                continue;
            }

            if ($item->quantity > $available) {
                $item->update(['quantity' => $available]);
            }
        }

        $items = CartItem::with(['product.images', 'product.seller.sellerProfile', 'product.category'])
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json([
            'data' => $items->map(fn (CartItem $item) => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal(),
                'product' => new ProductResource($item->product),
            ])->values(),
            'subtotal' => round($items->sum(fn ($item) => $item->subtotal()), 2),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['integer', 'min:1', 'max:99'],
        ]);

        $product = Product::visibleInShop()
            ->where('id', $validated['product_id'])
            ->firstOrFail();

        $quantity = (int) ($validated['quantity'] ?? 1);
        $userId = $request->user()->id;

        try {
            DB::transaction(function () use ($userId, $product, $quantity) {
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

                $cartItem = CartItem::withTrashed()
                    ->where('user_id', $userId)
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                $desired = $quantity;
                if ($cartItem && ! $cartItem->trashed()) {
                    $desired = $cartItem->quantity + $quantity;
                }

                $max = ProductStock::maxCartQuantity($product);
                if ($max < 1 || $desired > $max) {
                    throw new \RuntimeException(ProductStock::exceededMessage($product));
                }

                if (! $cartItem) {
                    CartItem::create([
                        'user_id' => $userId,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                    ]);

                    return;
                }

                if ($cartItem->trashed()) {
                    $cartItem->restore();
                    $cartItem->quantity = $quantity;
                    $cartItem->save();

                    return;
                }

                $cartItem->quantity = min($max, $cartItem->quantity + $quantity);
                $cartItem->save();
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $this->analytics->recordCartAdd($product, $quantity);

        return $this->index($request)->setStatusCode(201);
    }

    public function update(Request $request, CartItem $cartItem): JsonResponse
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cartItem->loadMissing('product');
        $product = $cartItem->product;

        if (! $product) {
            return response()->json(['message' => 'This product is no longer available.'], 422);
        }

        $desired = (int) $validated['quantity'];
        $max = ProductStock::maxCartQuantity($product);

        if ($max < 1 || $desired > $max) {
            return response()->json(['message' => ProductStock::exceededMessage($product)], 422);
        }

        $cartItem->update(['quantity' => $desired]);

        return $this->index($request);
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);

        $cartItem->delete();

        return $this->index($request);
    }
}
