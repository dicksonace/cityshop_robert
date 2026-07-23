<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FundsReleaseStatus;
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

        $base = OrderItem::query()
            ->with([
                'order:id,order_number,buyer_id,seller_id,payment_channel,payment_status,shipping_cost,total',
                'order.buyer:id,name,email,mobile',
                'seller:id,name,email,mobile',
                'product:id,name',
                'fundsReviewer:id,name',
            ]);

        $items = match ($status) {
            'held' => $base->where('funds_release_status', FundsReleaseStatus::Held),
            'released' => $base->where('funds_release_status', FundsReleaseStatus::Released),
            'all' => $base->where(function ($q) {
                $q->whereIn('funds_release_status', [
                    FundsReleaseStatus::Pending,
                    FundsReleaseStatus::Held,
                    FundsReleaseStatus::Released,
                ])->orWhere(function ($inner) {
                    $inner->whereNull('funds_release_status')
                        ->whereIn('status', OrderService::fundsReleaseEligibleStatuses());
                });
            })->whereHas('order', function ($q) {
                $q->where(function ($channel) {
                    $channel->where('payment_channel', \App\Enums\PaymentChannel::Marketplace)
                        ->orWhereNull('payment_channel');
                });
            }),
            default => $this->orderService->pendingFundReleaseItemsQuery()->with([
                'order:id,order_number,buyer_id,seller_id,payment_channel,payment_status,shipping_cost,total',
                'order.buyer:id,name,email,mobile',
                'seller:id,name,email,mobile',
                'product:id,name',
                'fundsReviewer:id,name',
            ]),
        };

        $items = $items
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (OrderItem $item) => [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'seller_amount' => (float) $item->seller_amount,
                'commission_amount' => (float) $item->commission_amount,
                'status' => $item->status->value,
                'funds_release_status' => $item->funds_release_status?->value ?? 'pending',
                'funds_release_notes' => $item->funds_release_notes,
                'funds_released_at' => $item->funds_released_at?->toIso8601String(),
                'updated_at' => $item->updated_at?->toIso8601String(),
                'order' => $item->order ? [
                    'id' => $item->order->id,
                    'order_number' => $item->order->order_number,
                    'payment_channel' => $item->order->payment_channel?->value,
                    'shipping_cost' => (float) $item->order->shipping_cost,
                    'total' => (float) $item->order->total,
                    'buyer' => $item->order->buyer ? [
                        'id' => $item->order->buyer->id,
                        'name' => $item->order->buyer->name,
                        'email' => $item->order->buyer->email,
                        'mobile' => $item->order->buyer->mobile,
                    ] : null,
                ] : null,
                'seller' => $item->seller ? [
                    'id' => $item->seller->id,
                    'name' => $item->seller->name,
                    'email' => $item->seller->email,
                    'mobile' => $item->seller->mobile,
                ] : null,
                'reviewer' => $item->fundsReviewer ? [
                    'id' => $item->fundsReviewer->id,
                    'name' => $item->fundsReviewer->name,
                ] : null,
            ]);

        return Inertia::render('admin/pending-funds/index', [
            'items' => $items,
            'status' => $status,
            'counts' => [
                'pending' => $this->orderService->pendingFundReleaseItemsQuery()->count(),
                'held' => OrderItem::where('funds_release_status', FundsReleaseStatus::Held)->count(),
                'released' => OrderItem::where('funds_release_status', FundsReleaseStatus::Released)->count(),
            ],
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
