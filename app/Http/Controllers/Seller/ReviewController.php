<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    public function index(Request $request): Response
    {
        $reviews = Review::with(['user', 'product:id,name,slug,images'])
            ->whereHas('product', fn ($q) => $q->where('seller_id', $request->user()->id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $stats = [
            'total' => Review::whereHas('product', fn ($q) => $q->where('seller_id', $request->user()->id))->count(),
            'unreplied' => Review::whereHas('product', fn ($q) => $q->where('seller_id', $request->user()->id))
                ->whereNull('seller_reply')
                ->count(),
            'average' => round((float) Review::whereHas('product', fn ($q) => $q->where('seller_id', $request->user()->id))->avg('rating'), 1),
        ];

        return Inertia::render('seller/reviews/index', [
            'reviews' => $reviews,
            'stats' => $stats,
        ]);
    }

    public function reply(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->product?->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'seller_reply' => ['required', 'string', 'max:2000'],
        ]);

        $review->update([
            'seller_reply' => $validated['seller_reply'],
            'seller_replied_at' => now(),
        ]);

        return back()->with('success', 'Reply posted.');
    }
}
