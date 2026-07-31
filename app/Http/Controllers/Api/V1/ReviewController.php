<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
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
            $review = ReviewService::createReview(
                $request->user(),
                $item,
                (int) $validated['rating'],
                $validated['comment'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Thank you for your review!',
            'review' => [
                'id' => $review->id,
                'product_id' => $review->product_id,
                'order_id' => $review->order_id,
                'rating' => (int) $review->rating,
                'comment' => $review->comment,
            ],
        ], 201);
    }
}
