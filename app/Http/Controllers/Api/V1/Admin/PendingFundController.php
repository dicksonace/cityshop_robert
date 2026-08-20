<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PendingFundController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $this->orderService->releaseStuckSellerShipping();
        $status = $request->string('status', 'pending')->toString();
        if (! in_array($status, ['pending', 'held', 'released', 'all'], true)) {
            $status = 'pending';
        }

        $page = $this->orderService->pendingFundItemsQuery($status)
            ->latest('updated_at')
            ->paginate(min(max((int) $request->integer('per_page', 50), 1), 100));

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn (OrderItem $item) => $this->orderService->serializePendingFundItem($item))
                ->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'status' => $status,
            'counts' => $this->orderService->pendingFundCounts(),
        ]);
    }

    public function approve(Request $request, OrderItem $orderItem): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            if (! empty($validated['admin_notes'])) {
                $orderItem->update(['funds_release_notes' => $validated['admin_notes']]);
            }
            $released = $this->orderService->releaseSellerFunds($orderItem, $request->user()->id, true);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $total = round(($released['product'] ?? 0) + ($released['shipping'] ?? 0), 2);

        return response()->json([
            'message' => 'Funds released to seller Available balance (GH₵'.number_format($total, 2).').',
            'released' => $released,
        ]);
    }

    public function reject(Request $request, OrderItem $orderItem): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $this->orderService->holdSellerFunds(
                $orderItem,
                $validated['admin_notes'],
                $request->user()->id,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Funds held in pending. A dispute was opened for review.']);
    }
}
