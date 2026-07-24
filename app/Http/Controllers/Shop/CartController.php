<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\ProductAnalyticsService;
use App\Support\ProductStock;
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

        $stockWarning = null;

        foreach ($items as $item) {
            $product = $item->product;
            if (! $product || $product->is_preorder) {
                continue;
            }

            $available = ProductStock::available($product) ?? 0;

            if ($available < 1) {
                $item->delete();
                $stockWarning = ($stockWarning ?? '').($stockWarning ? ' ' : '')
                    ."{$product->name} is out of stock and was removed from your cart.";
                continue;
            }

            if ($item->quantity > $available) {
                $item->update(['quantity' => $available]);
                $stockWarning = ProductStock::exceededMessage($product);
            }
        }

        $items = CartItem::with(['product.images', 'product.seller.sellerProfile'])
            ->where('user_id', $request->user()->id)
            ->get();

        $subtotal = $items->sum(fn ($item) => $item->subtotal());

        if ($stockWarning) {
            session()->flash('error', $stockWarning);
        }

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
            return back()->with('error', $e->getMessage());
        }

        $this->analytics->recordCartAdd($product, $quantity);

        return back()->with('success', 'Added to cart!');
    }

    public function update(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cartItem->loadMissing('product');
        $product = $cartItem->product;

        if (! $product) {
            return back()->with('error', 'This product is no longer available.');
        }

        $desired = (int) $validated['quantity'];
        $max = ProductStock::maxCartQuantity($product);

        if ($max < 1 || $desired > $max) {
            return back()->with('error', ProductStock::exceededMessage($product));
        }

        $cartItem->update(['quantity' => $desired]);

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(Request $request, CartItem $cartItem): RedirectResponse
    {
        abort_unless($cartItem->user_id === $request->user()->id, 403);

        $cartItem->delete();

        return back()->with('success', 'Item removed from cart.');
    }
}
