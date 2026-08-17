<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sellerId = $request->user()->id;
        $filter = $request->string('filter', 'all')->toString();

        $query = Review::with(['user', 'product.images'])
            ->whereHas('product', fn ($q) => $q->where('seller_id', $sellerId))
            ->latest();

        if ($filter === 'unreplied') {
            $query->whereNull('seller_reply');
        }

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);
        $reviews = $query->paginate($perPage);

        $base = Review::whereHas('product', fn ($q) => $q->where('seller_id', $sellerId));

        return response()->json([
            'data' => $reviews->getCollection()->map(fn (Review $review) => $this->serialize($review))->values(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
            'stats' => [
                'total' => (clone $base)->count(),
                'unreplied' => (clone $base)->whereNull('seller_reply')->count(),
                'average' => round((float) (clone $base)->avg('rating'), 1),
            ],
        ]);
    }

    public function reply(Request $request, Review $review): JsonResponse
    {
        $review->loadMissing('product.images', 'user');
        abort_unless($review->product?->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'seller_reply' => ['required', 'string', 'max:2000'],
        ]);

        $review->update([
            'seller_reply' => $validated['seller_reply'],
            'seller_replied_at' => now(),
        ]);

        return response()->json([
            'message' => 'Reply posted.',
            'data' => $this->serialize($review->fresh(['user', 'product.images'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Review $review): array
    {
        $image = $review->product?->images->firstWhere('is_primary', true)
            ?? $review->product?->images->first();

        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'seller_reply' => $review->seller_reply,
            'seller_replied_at' => $review->seller_replied_at?->toIso8601String(),
            'created_at' => $review->created_at?->toIso8601String(),
            'buyer_name' => $review->user?->name,
            'product' => $review->product ? [
                'id' => $review->product->id,
                'name' => $review->product->name,
                'image' => $this->publicUrl($image?->path),
            ] : null,
        ];
    }

    private function publicUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return str_starts_with($path, 'http')
            ? $path
            : Storage::disk('public')->url($path);
    }
}
