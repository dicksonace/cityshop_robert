<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\ProductAnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private ProductAnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        $items = CartItem::with(['product.images', 'product.seller.sellerProfile'])
            ->where('user_id', $request->user()->id)
            ->get();

        $subtotal = $items->sum(fn ($item) => $item->subtotal());

        return Inertia::render('shop/cart', [
            'items' => $items,
            'subtotal' => $subtotal,
        ]);
    }

    public function store(Request $request): RedirectResponse
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

        DB::transaction(function () use ($userId, $product, $quantity) {
            $cartItem = CartItem::withTrashed()
                ->where('user_id', $userId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if (! $cartItem) {
                CartItem::create([
                    'user_id' => $userId,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                ]);

                return;
            }

            if ($cartItem->trashed()) {
                // Soft-deleted rows keep the old quantity — start fresh at the requested amount.
                $cartItem->restore();
                $cartItem->quantity = $quantity;
                $cartItem->save();

                return;
            }

            $cartItem->quantity = min(99, $cartItem->quantity + $quantity);
            $cartItem->save();
        });

        $this->analytics->recordCartAdd($product, $quantity);

        return back()->with('success', 'Added to cart!');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cartItem->update($validated);

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);

        $cartItem->delete();

        return back()->with('success', 'Item removed from cart.');
    }
}
