<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    private const STAGE_MAP = [
        'new' => ['status' => OrderStatus::Pending],
        'processing' => ['status' => OrderStatus::Processing],
        'packing' => ['status' => OrderStatus::Packed],
        'delivery' => ['status' => OrderStatus::Shipped],
        'awaiting' => ['status' => OrderStatus::AwaitingConfirmation],
        'completed' => ['status' => OrderStatus::Delivered],
        'cancelled' => ['statuses' => [OrderStatus::Cancelled, OrderStatus::Refunded]],
        'all' => [],
    ];

    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): Response|RedirectResponse
    {
        if ($request->filled('status')) {
            $stage = $this->legacyStatusToStage($request->string('status')->toString());

            return redirect()->route('seller.orders.stage', $stage);
        }

        return $this->hub($request);
    }

    public function hub(Request $request): Response
    {
        $sellerId = $request->user()->id;
        $counts = $this->orderCounts($sellerId);

        $urgent = OrderItem::with(['order.buyer', 'product.images'])
            ->where('seller_id', $sellerId)
            ->whereIn('status', [
                OrderStatus::Pending,
                OrderStatus::Processing,
                OrderStatus::Packed,
                OrderStatus::Shipped,
            ])
            ->latest()
            ->limit(6)
            ->get();

        $recentCompleted = OrderItem::with(['order.buyer', 'product.images'])
            ->where('seller_id', $sellerId)
            ->where('status', OrderStatus::Delivered)
            ->latest()
            ->limit(4)
            ->get();

        return Inertia::render('seller/orders/hub', [
            'counts' => $counts,
            'urgentOrders' => $urgent,
            'recentCompleted' => $recentCompleted,
            'needsAction' => $counts['pending'] + $counts['processing'] + $counts['packed'] + $counts['shipped'],
        ]);
    }

    public function stage(Request $request, string $stage): Response|RedirectResponse
    {
        if (! array_key_exists($stage, self::STAGE_MAP)) {
            return redirect()->route('seller.orders.index');
        }

        $sellerId = $request->user()->id;
        $config = self::STAGE_MAP[$stage];

        $query = OrderItem::with(['order.buyer', 'product.images'])
            ->where('seller_id', $sellerId);

        if (isset($config['status'])) {
            $query->where('status', $config['status']);
        } elseif (isset($config['statuses'])) {
            $query->whereIn('status', $config['statuses']);
        }

        $orders = $query->latest()->paginate(12)->withQueryString();

        return Inertia::render('seller/orders/stage', [
            'orders' => $orders,
            'counts' => $this->orderCounts($sellerId),
            'stage' => $stage,
        ]);
    }

    public function show(Request $request, OrderItem $orderItem): Response
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);

        $orderItem->load([
            'order.buyer',
            'order.checkout',
            'product.images',
            'dispute',
        ]);

        return Inertia::render('seller/orders/show', [
            'orderItem' => $orderItem,
            'backStage' => $this->statusToStage($orderItem->status),
        ]);
    }

    public function update(Request $request, OrderItem $orderItem): RedirectResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:processing,packed,shipped,awaiting_confirmation'],
            'vehicle_number' => ['nullable', 'string', 'max:50'],
            'driver_phone' => ['nullable', 'string', 'max:30'],
            'package_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $becomingShipped = $validated['status'] === 'shipped' && $orderItem->status !== OrderStatus::Shipped;

        if ($becomingShipped) {
            $request->validate([
                'vehicle_number' => ['required', 'string', 'max:50'],
                'driver_phone' => ['required', 'string', 'max:30'],
            ]);
            $validated['vehicle_number'] = $request->string('vehicle_number')->toString();
            $validated['driver_phone'] = $request->string('driver_phone')->toString();
        }

        if ($request->hasFile('package_image')) {
            $validated['package_image'] = $request->file('package_image')->store('order-packages', 'public');
        } else {
            unset($validated['package_image']);
        }

        try {
            $this->orderService->updateOrderItemStatus($orderItem, $validated);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $nextStage = $this->statusToStage(OrderStatus::from($validated['status']));

        return redirect()
            ->route('seller.orders.stage', $nextStage)
            ->with('success', 'Order moved to the next stage.');
    }

    public function reject(Request $request, OrderItem $orderItem): RedirectResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->orderService->rejectOrderItem($orderItem, $validated['rejection_reason']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('seller.orders.stage', 'cancelled')
            ->with('success', 'Order rejected. Refund credited to buyer wallet.');
    }

    /**
     * @return array<string, int>
     */
    private function orderCounts(int $sellerId): array
    {
        $base = OrderItem::where('seller_id', $sellerId);

        return [
            'all' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', OrderStatus::Pending)->count(),
            'processing' => (clone $base)->where('status', OrderStatus::Processing)->count(),
            'packed' => (clone $base)->where('status', OrderStatus::Packed)->count(),
            'shipped' => (clone $base)->where('status', OrderStatus::Shipped)->count(),
            'awaiting_confirmation' => (clone $base)->where('status', OrderStatus::AwaitingConfirmation)->count(),
            'delivered' => (clone $base)->where('status', OrderStatus::Delivered)->count(),
            'cancelled' => (clone $base)->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded])->count(),
        ];
    }

    private function legacyStatusToStage(string $status): string
    {
        return match ($status) {
            'pending' => 'new',
            'packed' => 'packing',
            'shipped' => 'delivery',
            'awaiting_confirmation' => 'awaiting',
            'delivered' => 'completed',
            'cancelled', 'refunded' => 'cancelled',
            'all' => 'all',
            default => $status === 'processing' ? 'processing' : 'new',
        };
    }

    private function statusToStage(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Pending => 'new',
            OrderStatus::Processing => 'processing',
            OrderStatus::Packed => 'packing',
            OrderStatus::Shipped => 'delivery',
            OrderStatus::AwaitingConfirmation => 'awaiting',
            OrderStatus::Delivered => 'completed',
            OrderStatus::Cancelled, OrderStatus::Refunded => 'cancelled',
            default => 'new',
        };
    }
}
