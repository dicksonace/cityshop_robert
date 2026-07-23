<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(): Response
    {
        $orders = Order::with(['buyer', 'items', 'checkout:id,checkout_number'])
            ->latest()
            ->paginate(15);

        return Inertia::render('admin/orders/index', [
            'orders' => $orders,
        ]);
    }

    public function awaitingDirectPayment(Request $request): Response
    {
        $claim = $request->string('claim')->toString();
        if (! in_array($claim, ['all', 'awaiting_claim', 'claimed'], true)) {
            $claim = 'all';
        }

        $orders = $this->orders->awaitingDirectPaymentOrdersQuery($claim)
            ->with([
                'buyer',
                'seller.sellerProfile',
                'checkout:id,checkout_number',
                'sellerPaymentMethod',
                'items',
            ])
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(function (Order $order) {
                $method = $order->sellerPaymentMethod;
                $hasClaim = filled($order->direct_payment_reference) || filled($order->direct_payment_proof_path);

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => (float) $order->total,
                    'status' => $order->status->value,
                    'payment_status' => $order->payment_status->value,
                    'created_at' => $order->created_at?->toIso8601String(),
                    'hours_waiting' => $order->created_at ? (int) $order->created_at->diffInHours(now()) : null,
                    'claim_status' => $hasClaim ? 'claimed' : 'awaiting_claim',
                    'direct_payment_reference' => $order->direct_payment_reference,
                    'direct_payment_proof_path' => $order->direct_payment_proof_path,
                    'direct_payment_rejection_reason' => $order->direct_payment_rejection_reason,
                    'checkout_number' => $order->checkout?->checkout_number,
                    'buyer' => [
                        'id' => $order->buyer?->id,
                        'name' => $order->buyer?->name,
                        'email' => $order->buyer?->email,
                        'mobile' => $order->buyer?->mobile,
                    ],
                    'seller' => [
                        'id' => $order->seller?->id,
                        'name' => $order->seller?->name,
                        'store' => $order->seller?->sellerProfile?->store_name
                            ?? $order->seller?->sellerProfile?->business_name,
                    ],
                    'payment_method' => $method ? [
                        'type' => $method->type?->value ?? $method->type,
                        'account_name' => $method->account_name,
                        'account_number' => $method->account_number,
                        'network' => $method->network,
                        'bank_name' => $method->bank_name,
                    ] : null,
                    'items_count' => $order->items->count(),
                ];
            });

        return Inertia::render('admin/orders/awaiting-direct', [
            'orders' => $orders,
            'claim' => $claim,
            'count' => $this->orders->awaitingDirectPaymentOrdersQuery('all')->count(),
            'awaiting_claim_count' => $this->orders->awaitingDirectPaymentOrdersQuery('awaiting_claim')->count(),
            'claimed_count' => $this->orders->awaitingDirectPaymentOrdersQuery('claimed')->count(),
        ]);
    }

    public function unprocessed(Request $request): Response
    {
        $hours = max(1, (int) $request->integer('hours', 24));

        $items = $this->orders->staleUnprocessedItemsQuery($hours)
            ->with([
                'order.buyer',
                'order.checkout:id,checkout_number',
                'order.payments',
                'seller.sellerProfile',
                'product.images',
            ])
            ->paginate(20)
            ->withQueryString()
            ->through(function (OrderItem $item) {
                $paidAt = $this->orders->itemPaidAt($item);

                return [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => $item->lineTotal(),
                    'status' => $item->status->value,
                    'paid_at' => $paidAt?->toIso8601String(),
                    'hours_waiting' => $paidAt ? (int) $paidAt->diffInHours(now()) : null,
                    'order' => [
                        'id' => $item->order->id,
                        'order_number' => $item->order->order_number,
                        'shipping_cost' => (float) $item->order->shipping_cost,
                        'payment_channel' => $item->order->payment_channel?->value ?? 'marketplace',
                        'checkout_number' => $item->order->checkout?->checkout_number,
                    ],
                    'buyer' => [
                        'id' => $item->order->buyer?->id,
                        'name' => $item->order->buyer?->name,
                        'email' => $item->order->buyer?->email,
                        'mobile' => $item->order->buyer?->mobile,
                    ],
                    'seller' => [
                        'id' => $item->seller?->id,
                        'name' => $item->seller?->name,
                        'store' => $item->seller?->sellerProfile?->store_name
                            ?? $item->seller?->sellerProfile?->business_name,
                    ],
                ];
            });

        return Inertia::render('admin/orders/unprocessed', [
            'items' => $items,
            'hours' => $hours,
            'count' => $this->orders->staleUnprocessedItemsQuery($hours)->count(),
        ]);
    }

    public function cancelUnprocessed(Request $request, OrderItem $orderItem): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $reason = $validated['reason']
            ?? 'Admin cancelled: order does not look like it will go through.';

        if (! str_starts_with(mb_strtolower($reason), 'admin')) {
            $reason = 'Admin: '.$reason;
        }

        try {
            $this->orders->adminCancelUnprocessedItem($orderItem, $reason);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            'Order cancelled. The buyer has been refunded to their CityShop wallet.'
        );
    }

    public function awaitingConfirmation(): Response
    {
        $items = OrderItem::query()
            ->where('status', \App\Enums\OrderStatus::AwaitingConfirmation)
            ->with([
                'order.buyer',
                'order.checkout:id,checkout_number',
                'seller.sellerProfile',
            ])
            ->latest('updated_at')
            ->paginate(20)
            ->through(function (OrderItem $item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => $item->lineTotal(),
                    'status' => $item->status->value,
                    'updated_at' => $item->updated_at?->toIso8601String(),
                    'order' => [
                        'id' => $item->order->id,
                        'order_number' => $item->order->order_number,
                        'payment_channel' => $item->order->payment_channel?->value ?? 'marketplace',
                        'checkout_number' => $item->order->checkout?->checkout_number,
                    ],
                    'buyer' => [
                        'id' => $item->order->buyer?->id,
                        'name' => $item->order->buyer?->name,
                        'email' => $item->order->buyer?->email,
                        'mobile' => $item->order->buyer?->mobile,
                    ],
                    'seller' => [
                        'id' => $item->seller?->id,
                        'name' => $item->seller?->name,
                        'store' => $item->seller?->sellerProfile?->store_name
                            ?? $item->seller?->sellerProfile?->business_name,
                    ],
                ];
            });

        return Inertia::render('admin/orders/confirm-delivery', [
            'items' => $items,
            'count' => OrderItem::where('status', \App\Enums\OrderStatus::AwaitingConfirmation)->count(),
        ]);
    }

    public function confirmDelivery(OrderItem $orderItem): RedirectResponse
    {
        try {
            // Admin confirm completes the order (buyer can review) — does not release funds.
            $this->orders->confirmBuyerDelivery($orderItem, releaseFunds: false);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with(
            'success',
            'Delivery confirmed on behalf of the buyer. They can leave a review. Funds stay pending until you release them under Pending Funds (or the buyer already triggered release).'
        );
    }

    public function cancellations(Request $request): Response
    {
        $items = OrderItem::query()
            ->with(['order.buyer', 'seller.sellerProfile'])
            ->where('status', \App\Enums\OrderStatus::Cancelled)
            ->where('cancelled_by', \App\Support\OrderCancellation::BY_SELLER)
            ->latest('cancelled_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (OrderItem $item) {
                return [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'line_total' => $item->lineTotal(),
                    'rejection_reason' => $item->rejection_reason,
                    'cancellation_code' => $item->cancellation_code,
                    'cancellation_label' => \App\Support\OrderCancellation::label($item->cancellation_code),
                    'cancelled_at' => $item->cancelled_at?->toIso8601String(),
                    'refund_status' => $item->refund_status,
                    'order' => [
                        'id' => $item->order->id,
                        'order_number' => $item->order->order_number,
                        'payment_channel' => $item->order->payment_channel?->value,
                    ],
                    'buyer' => [
                        'name' => $item->order->buyer?->name,
                    ],
                    'seller' => [
                        'id' => $item->seller_id,
                        'name' => $item->seller?->name,
                        'store' => $item->seller?->sellerProfile?->store_name
                            ?? $item->seller?->sellerProfile?->business_name,
                    ],
                ];
            });

        $rates = OrderItem::query()
            ->selectRaw('seller_id')
            ->selectRaw('COUNT(*) as total_items')
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' AND cancelled_by = 'seller' THEN 1 ELSE 0 END) as seller_cancels")
            ->groupBy('seller_id')
            ->havingRaw("SUM(CASE WHEN status = 'cancelled' AND cancelled_by = 'seller' THEN 1 ELSE 0 END) > 0")
            ->orderByDesc('seller_cancels')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $seller = \App\Models\User::with('sellerProfile')->find($row->seller_id);
                $total = max(1, (int) $row->total_items);
                $cancels = (int) $row->seller_cancels;

                return [
                    'seller_id' => $row->seller_id,
                    'name' => $seller?->name,
                    'store' => $seller?->sellerProfile?->store_name ?? $seller?->sellerProfile?->business_name,
                    'total_items' => $total,
                    'seller_cancels' => $cancels,
                    'rate' => round(($cancels / $total) * 100, 1),
                ];
            });

        return Inertia::render('admin/orders/cancellations', [
            'items' => $items,
            'highCancelSellers' => $rates,
        ]);
    }

    public function show(Order $order): Response
    {
        $order->load(['buyer', 'items.product', 'items.seller', 'checkout.orders', 'checkout.payments', 'checkout.invoices', 'sellerPaymentMethod']);

        return Inertia::render('admin/orders/show', [
            'order' => $order,
            'checkout' => $order->checkout,
        ]);
    }
}
