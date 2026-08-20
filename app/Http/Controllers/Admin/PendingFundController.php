<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PendingFundController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): Response
    {
        // One-pass repair: product already released but shipping still pending.
        $this->orderService->releaseStuckSellerShipping();

        $status = $request->get('status', 'pending');

        $items = $this->orderService->pendingFundItemsQuery($status)
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (OrderItem $item) => $this->orderService->serializePendingFundItem($item));

        return Inertia::render('admin/pending-funds/index', [
            'items' => $items,
            'status' => $status,
            'counts' => $this->orderService->pendingFundCounts(),
        ]);
    }

    public function approve(Request $request, OrderItem $orderItem): RedirectResponse
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
            return back()->with('error', $e->getMessage());
        }

        $total = round(($released['product'] ?? 0) + ($released['shipping'] ?? 0), 2);
        $parts = ['product GH₵'.number_format((float) $released['product'], 2)];
        if (($released['shipping'] ?? 0) > 0) {
            $parts[] = 'shipping GH₵'.number_format((float) $released['shipping'], 2);
        }

        return back()->with(
            'success',
            'Funds released to seller Available balance (GH₵'.number_format($total, 2).': '.implode(' + ', $parts).'). Buyer still confirms delivery to complete the order (no second release).',
        );
    }

    public function reject(Request $request, OrderItem $orderItem): RedirectResponse
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
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            'Funds held in pending. A dispute was opened for review.',
        );
    }
}
