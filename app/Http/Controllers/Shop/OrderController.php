<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Review;
use App\Services\OrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): Response
    {
        $buyerId = $request->user()->id;
        $tab = (string) $request->query('tab', 'all');

        $counts = [
            'all' => Checkout::where('buyer_id', $buyerId)->count(),
            'unpaid' => Checkout::where('buyer_id', $buyerId)
                ->where('status', '!=', OrderStatus::Cancelled)
                ->whereHas('orders', fn (Builder $q) => $q
                    ->where('payment_status', PaymentStatus::Pending)
                    ->where('status', '!=', OrderStatus::Cancelled))
                ->count(),
            'processing' => Checkout::where('buyer_id', $buyerId)
                ->whereHas('orders', fn (Builder $q) => $q
                    ->where('payment_status', PaymentStatus::Paid)
                    ->whereIn('status', [OrderStatus::Pending, OrderStatus::Processing, OrderStatus::Packed]))
                ->count(),
            'delivery' => Checkout::where('buyer_id', $buyerId)
                ->whereHas('orders', fn (Builder $q) => $q->where('status', OrderStatus::Shipped))
                ->count(),
            'confirm' => Checkout::where('buyer_id', $buyerId)
                ->whereHas('orders', fn (Builder $q) => $q->where('status', OrderStatus::AwaitingConfirmation))
                ->count(),
            'refunds' => Checkout::where('buyer_id', $buyerId)
                ->whereHas('orders', function (Builder $q) {
                    $q->where(function (Builder $inner) {
                        $inner->where('status', OrderStatus::Refunded)
                            ->orWhere('payment_status', PaymentStatus::Refunded)
                            ->orWhereHas('items.dispute');
                    });
                })
                ->count(),
            'completed' => Checkout::where('buyer_id', $buyerId)
                ->whereHas('orders', fn (Builder $q) => $q
                    ->where('status', OrderStatus::Delivered)
                    ->where('payment_status', PaymentStatus::Paid))
                ->count(),
            'cancelled' => Checkout::where('buyer_id', $buyerId)
                ->whereHas('orders', fn (Builder $q) => $q->where('status', OrderStatus::Cancelled))
                ->count(),
            'review' => $this->pendingReviewCount($buyerId) > 0
                ? Checkout::where('buyer_id', $buyerId)
                    ->whereHas('orders.items', function (Builder $q) use ($buyerId) {
                        $q->where('status', OrderStatus::Delivered)
                            ->whereNotExists(function ($sub) use ($buyerId) {
                                $sub->selectRaw('1')
                                    ->from('reviews')
                                    ->whereColumn('reviews.order_id', 'order_items.order_id')
                                    ->whereColumn('reviews.product_id', 'order_items.product_id')
                                    ->where('reviews.user_id', $buyerId);
                            });
                    })
                    ->count()
                : 0,
            'invoices' => Checkout::where('buyer_id', $buyerId)
                ->whereHas('invoices', fn (Builder $q) => $q
                    ->where('user_id', $buyerId)
                    ->whereIn('type', ['customer', 'customer_master']))
                ->count(),
        ];

        $purchases = Checkout::with([
            'orders.items.product.images',
            'orders.items.dispute',
            'orders.seller.sellerProfile',
            'invoices' => fn ($q) => $q
                ->where('user_id', $buyerId)
                ->whereIn('type', ['customer', 'customer_master']),
        ])
            ->where('buyer_id', $buyerId)
            ->when($tab !== 'all', fn (Builder $q) => $this->applyTabFilter($q, $tab, $buyerId))
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(function (Checkout $checkout) use ($buyerId) {
                $reviewedByOrder = Review::where('user_id', $buyerId)
                    ->whereIn('order_id', $checkout->orders->pluck('id'))
                    ->get()
                    ->groupBy('order_id');

                return [
                    'id' => $checkout->id,
                    'checkout_number' => $checkout->checkout_number,
                    'payment_status' => $checkout->payment_status->value,
                    'status' => $checkout->status->value,
                    'subtotal' => (float) $checkout->subtotal,
                    'shipping_cost' => (float) $checkout->shipping_cost,
                    'total' => (float) $checkout->total,
                    'created_at' => $checkout->created_at?->toIso8601String(),
                    'invoice' => $checkout->invoices->first() ? [
                        'id' => $checkout->invoices->first()->id,
                        'invoice_number' => $checkout->invoices->first()->invoice_number,
                    ] : null,
                    'packages' => $checkout->orders->map(function (Order $order) use ($reviewedByOrder) {
                        $sellerProfile = $order->seller?->sellerProfile;
                        $firstItem = $order->items->first();
                        $awaiting = $order->items->firstWhere('status', OrderStatus::AwaitingConfirmation);
                        $shippedOrDelivered = $order->items->contains(
                            fn (OrderItem $item) => in_array($item->status, [OrderStatus::Shipped, OrderStatus::Delivered, OrderStatus::AwaitingConfirmation], true)
                        );
                        $hasOpenDispute = $order->items->contains(
                            fn (OrderItem $item) => $item->relationLoaded('dispute') && $item->dispute !== null
                        );
                        $reviewedProductIds = $reviewedByOrder->get($order->id)?->pluck('product_id')->all() ?? [];
                        $needsReview = $order->items->contains(
                            fn (OrderItem $item) => $item->status === OrderStatus::Delivered
                                && ! in_array($item->product_id, $reviewedProductIds, true)
                        );

                        return [
                            'id' => $order->id,
                            'order_number' => $order->order_number,
                            'status' => $order->status->value,
                            'payment_status' => $order->payment_status->value,
                            'payment_method' => $order->payment_method,
                            'subtotal' => (float) $order->subtotal,
                            'shipping_cost' => (float) $order->shipping_cost,
                            'total' => (float) $order->total,
                            'seller_name' => $sellerProfile?->displayName() ?? $order->seller?->name ?? 'Seller',
                            'store_slug' => $sellerProfile?->slug,
                            'item_count' => $order->items->sum('quantity'),
                            'product_count' => $order->items->count(),
                            'image' => $firstItem?->product?->images?->first()?->path,
                            'first_product_name' => $firstItem?->product_name,
                            'first_product_slug' => $firstItem?->product?->slug,
                            'awaiting_item_id' => $awaiting?->id,
                            'needs_review' => $needsReview,
                            'can_refund' => $shippedOrDelivered && ! $hasOpenDispute && $order->status !== OrderStatus::Cancelled,
                            'driver_phone' => $order->items->first(fn (OrderItem $i) => filled($i->driver_phone))?->driver_phone,
                            'vehicle_number' => $order->items->first(fn (OrderItem $i) => filled($i->vehicle_number))?->vehicle_number,
                        ];
                    })->values(),
                ];
            });

        return Inertia::render('shop/orders', [
            'purchases' => $purchases,
            'counts' => $counts,
            'tab' => $tab,
        ]);
    }

    private function pendingReviewCount(int $buyerId): int
    {
        return OrderItem::query()
            ->whereHas('order', fn (Builder $q) => $q->where('buyer_id', $buyerId))
            ->where('status', OrderStatus::Delivered)
            ->whereNotExists(function ($query) use ($buyerId) {
                $query->selectRaw('1')
                    ->from('reviews')
                    ->whereColumn('reviews.order_id', 'order_items.order_id')
                    ->whereColumn('reviews.product_id', 'order_items.product_id')
                    ->where('reviews.user_id', $buyerId);
            })
            ->count();
    }

    private function applyTabFilter(Builder $query, string $tab, int $buyerId): void
    {
        match ($tab) {
            'unpaid' => $query
                ->where('status', '!=', OrderStatus::Cancelled)
                ->whereHas('orders', fn (Builder $q) => $q
                    ->where('payment_status', PaymentStatus::Pending)
                    ->where('status', '!=', OrderStatus::Cancelled)),
            'processing' => $query->whereHas('orders', fn (Builder $q) => $q
                ->where('payment_status', PaymentStatus::Paid)
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Processing, OrderStatus::Packed])),
            'delivery' => $query->whereHas('orders', fn (Builder $q) => $q->where('status', OrderStatus::Shipped)),
            'confirm' => $query->whereHas('orders', fn (Builder $q) => $q->where('status', OrderStatus::AwaitingConfirmation)),
            'refunds' => $query->whereHas('orders', function (Builder $q) {
                $q->where(function (Builder $inner) {
                    $inner->where('status', OrderStatus::Refunded)
                        ->orWhere('payment_status', PaymentStatus::Refunded)
                        ->orWhereHas('items.dispute');
                });
            }),
            'completed' => $query->whereHas('orders', fn (Builder $q) => $q
                ->where('status', OrderStatus::Delivered)
                ->where('payment_status', PaymentStatus::Paid)),
            'cancelled' => $query->whereHas('orders', fn (Builder $q) => $q->where('status', OrderStatus::Cancelled)),
            'review' => $query->whereHas('orders.items', function (Builder $q) use ($buyerId) {
                $q->where('status', OrderStatus::Delivered)
                    ->whereNotExists(function ($sub) use ($buyerId) {
                        $sub->selectRaw('1')
                            ->from('reviews')
                            ->whereColumn('reviews.order_id', 'order_items.order_id')
                            ->whereColumn('reviews.product_id', 'order_items.product_id')
                            ->where('reviews.user_id', $buyerId);
                    });
            }),
            'invoices' => $query->whereHas('invoices', fn (Builder $q) => $q
                ->where('user_id', $buyerId)
                ->whereIn('type', ['customer', 'customer_master'])),
            default => null,
        };
    }

    public function show(Request $request, Order $order): Response|RedirectResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        // Prefer the purchase hub when there are sibling packages.
        if ($order->checkout_id) {
            $siblingCount = Order::where('checkout_id', $order->checkout_id)->count();
            if ($siblingCount > 1 && ! $request->boolean('package')) {
                return redirect()->route('checkouts.show', $order->checkout_id);
            }
        }

        $order->load([
            'items.product.images',
            'items.dispute',
            'seller.sellerProfile',
            'checkout',
        ]);

        $reviews = Review::where('order_id', $order->id)
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('product_id');

        return Inertia::render('shop/order-show', [
            'order' => $order,
            'reviews' => $reviews,
            'checkoutNumber' => $order->checkout?->checkout_number,
            'checkoutId' => $order->checkout_id,
        ]);
    }

    public function confirmDelivery(Request $request, Order $order, OrderItem $orderItem): RedirectResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_unless($orderItem->order_id === $order->id, 404);

        try {
            $this->orderService->confirmBuyerDelivery($orderItem);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Delivery confirmed. Seller funds stay pending until CityShop admin approves release.');
    }
}
