<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DisputeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'open')->toString();
        $sellerId = $request->user()->id;

        $query = Dispute::with(['order', 'buyer', 'orderItem.product.images'])
            ->where('seller_id', $sellerId)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest();

        $disputes = $query->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));
        $base = Dispute::where('seller_id', $sellerId);

        return response()->json([
            'data' => $disputes->getCollection()->map(fn (Dispute $dispute) => $this->serialize($dispute))->values(),
            'meta' => [
                'current_page' => $disputes->currentPage(),
                'last_page' => $disputes->lastPage(),
                'total' => $disputes->total(),
                'status' => $status,
            ],
            'counts' => [
                'open' => (clone $base)->where('status', DisputeStatus::Open)->count(),
                'under_review' => (clone $base)->where('status', DisputeStatus::UnderReview)->count(),
                'resolved_buyer' => (clone $base)->where('status', DisputeStatus::ResolvedBuyer)->count(),
                'resolved_seller' => (clone $base)->where('status', DisputeStatus::ResolvedSeller)->count(),
                'cancelled' => (clone $base)->where('status', DisputeStatus::Cancelled)->count(),
                'all' => (clone $base)->count(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Dispute $dispute): array
    {
        $image = $dispute->orderItem?->product?->images->firstWhere('is_primary', true)
            ?? $dispute->orderItem?->product?->images->first();

        return [
            'id' => $dispute->id,
            'reason' => $dispute->reason,
            'description' => $dispute->description,
            'status' => $dispute->status?->value ?? (string) $dispute->status,
            'resolution_notes' => $dispute->resolution_notes,
            'resolved_at' => $dispute->resolved_at?->toIso8601String(),
            'created_at' => $dispute->created_at?->toIso8601String(),
            'is_open' => $dispute->isOpen(),
            'order_number' => $dispute->order?->order_number,
            'order_item_id' => $dispute->order_item_id,
            'buyer_name' => $dispute->buyer?->name,
            'product_name' => $dispute->orderItem?->product_name ?: $dispute->orderItem?->product?->name,
            'product_image' => $this->publicUrl($image?->path),
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
