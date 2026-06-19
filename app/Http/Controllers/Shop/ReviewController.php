<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        $validated = $request->validate([
            'order_item_id' => ['required', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $item = OrderItem::with(['order', 'product'])
            ->where('id', $validated['order_item_id'])
            ->where('order_id', $order->id)
            ->firstOrFail();

        try {
            ReviewService::createReview(
                $request->user(),
                $item,
                (int) $validated['rating'],
                $validated['comment'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Thank you for your review!');
    }

    public function storeForProduct(Request $request, Product $product): RedirectResponse
    {
        if (! $product->isVisibleInShop()) {
            abort(404);
        }

        $validated = $request->validate([
            'order_item_id' => ['required', 'exists:order_items,id'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['required', 'string', 'max:1000'],
        ]);

        $item = OrderItem::with(['order', 'product'])
            ->where('id', $validated['order_item_id'])
            ->where('product_id', $product->id)
            ->firstOrFail();

        $reviewable = ReviewService::findReviewableItem($request->user(), $product);

        if (! $reviewable || $reviewable->id !== $item->id) {
            return back()->with('error', 'You are not eligible to review this product.');
        }

        try {
            ReviewService::createReview(
                $request->user(),
                $item,
                (int) $validated['rating'],
                $validated['comment'],
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Thank you for your review!');
    }
}
