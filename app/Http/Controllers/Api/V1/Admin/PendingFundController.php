<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FundsReleaseStatus;
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

        $items = match ($status) {
            'held' => OrderItem::where('funds_release_status', FundsReleaseStatus::Held),
            'released' => OrderItem::where('funds_release_status', FundsReleaseStatus::Released),
            default => $this->orderService->pendingFundReleaseItemsQuery(),
        };

        $page = $items->with(['order:id,order_number,buyer_id', 'order.buyer:id,name,mobile', 'seller:id,name'])
            ->latest('updated_at')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $page->getCollection()->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'seller_amount' => (float) $item->seller_amount,
                'status' => $item->status?->value,
                'funds_release_status' => $item->funds_release_status?->value ?? 'pending',
                'order_number' => $item->order?->order_number,
                'buyer_name' => $item->order?->buyer?->name,
                'seller_name' => $item->seller?->name,
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'status' => $status,
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
            $this->orderService->releaseSellerFunds($orderItem, $request->user()->id, true);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Funds released to the seller.']);
    }

    public function reject(Request $request, OrderItem $orderItem): JsonResponse
    {
        $validated = $request->validate([
            'admin_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $this->orderService->holdSellerFunds($orderItem, $validated['admin_notes'], $request->user()->id);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Funds held. A dispute was opened.']);
    }
}
