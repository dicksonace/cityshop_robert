<?php

namespace App\Http\Controllers\Shop;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
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
            'all' => Order::where('buyer_id', $buyerId)->count(),
            'unpaid' => Order::where('buyer_id', $buyerId)->where('payment_status', PaymentStatus::Pending)->count(),
            'processing' => Order::where('buyer_id', $buyerId)
                ->where('payment_status', PaymentStatus::Paid)
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Processing, OrderStatus::Packed])
                ->count(),
            'delivery' => Order::where('buyer_id', $buyerId)->where('status', OrderStatus::Shipped)->count(),
            'confirm' => Order::where('buyer_id', $buyerId)->where('status', OrderStatus::AwaitingConfirmation)->count(),
            'refunds' => Order::where('buyer_id', $buyerId)
                ->where(function (Builder $q) {
                    $q->where('status', OrderStatus::Refunded)
                        ->orWhere('payment_status', PaymentStatus::Refunded)
                        ->orWhereHas('items.dispute');
                })
                ->count(),
            'completed' => Order::where('buyer_id', $buyerId)
                ->where('status', OrderStatus::Delivered)
                ->where('payment_status', PaymentStatus::Paid)
                ->count(),
            'cancelled' => Order::where('buyer_id', $buyerId)->where('status', OrderStatus::Cancelled)->count(),
            'review' => $this->pendingReviewCount($buyerId),
            'invoices' => Order::where('buyer_id', $buyerId)
                ->whereHas('checkout.invoices', fn (Builder $q) => $q
                    ->where('user_id', $buyerId)
                    ->whereIn('type', ['customer', 'customer_master']))
                ->count(),
        ];

        $orders = Order::with([
            'items.product.images',
            'seller.sellerProfile',
            'checkout.invoices' => fn ($q) => $q
                ->where('user_id', $buyerId)
                ->whereIn('type', ['customer', 'customer_master']),
        ])
            ->where('buyer_id', $buyerId)
            ->when($tab !== 'all', fn (Builder $q) => $this->applyTabFilter($q, $tab, $buyerId))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('shop/orders', [
            'orders' => $orders,
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
            'unpaid' => $query->where('payment_status', PaymentStatus::Pending),
            'processing' => $query
                ->where('payment_status', PaymentStatus::Paid)
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Processing, OrderStatus::Packed]),
            'delivery' => $query->where('status', OrderStatus::Shipped),
            'confirm' => $query->where('status', OrderStatus::AwaitingConfirmation),
            'refunds' => $query->where(function (Builder $q) {
                $q->where('status', OrderStatus::Refunded)
                    ->orWhere('payment_status', PaymentStatus::Refunded)
                    ->orWhereHas('items.dispute');
            }),
            'completed' => $query
                ->where('status', OrderStatus::Delivered)
                ->where('payment_status', PaymentStatus::Paid),
            'cancelled' => $query->where('status', OrderStatus::Cancelled),
            'review' => $query->whereHas('items', function (Builder $q) use ($buyerId) {
                $q->where('status', OrderStatus::Delivered)
                    ->whereNotExists(function ($sub) use ($buyerId) {
                        $sub->selectRaw('1')
                            ->from('reviews')
                            ->whereColumn('reviews.order_id', 'order_items.order_id')
                            ->whereColumn('reviews.product_id', 'order_items.product_id')
                            ->where('reviews.user_id', $buyerId);
                    });
            }),
            'invoices' => $query->whereHas('checkout.invoices', fn (Builder $q) => $q
                ->where('user_id', $buyerId)
                ->whereIn('type', ['customer', 'customer_master'])),
            default => null,
        };
    }

    public function show(Request $request, Order $order): Response|RedirectResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        if ($order->checkout_id) {
            return redirect()->route('checkouts.show', $order->checkout_id);
        }

        $order->load(['items.product.images', 'items.dispute']);

        $reviews = Review::where('order_id', $order->id)
            ->where('user_id', $request->user()->id)
            ->get()
            ->keyBy('product_id');

        return Inertia::render('shop/order-show', [
            'order' => $order,
            'reviews' => $reviews,
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

        return back()->with('success', 'Delivery confirmed. Thank you — you can now leave a review!');
    }
}
