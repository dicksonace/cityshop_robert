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
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        $query = OrderItem::with(['order.buyer', 'product.images'])
            ->where('seller_id', $request->user()->id);

        if ($status && $status !== 'all') {
            if ($status === 'active') {
                $query->whereIn('status', [OrderStatus::Pending, OrderStatus::Processing, OrderStatus::Packed, OrderStatus::Shipped]);
            } else {
                $query->where('status', $status);
            }
        }

        $orders = $query->latest()->paginate(20)->withQueryString();

        $counts = [
            'all' => OrderItem::where('seller_id', $request->user()->id)->count(),
            'pending' => OrderItem::where('seller_id', $request->user()->id)->where('status', OrderStatus::Pending)->count(),
            'processing' => OrderItem::where('seller_id', $request->user()->id)->where('status', OrderStatus::Processing)->count(),
            'packed' => OrderItem::where('seller_id', $request->user()->id)->where('status', OrderStatus::Packed)->count(),
            'shipped' => OrderItem::where('seller_id', $request->user()->id)->where('status', OrderStatus::Shipped)->count(),
            'delivered' => OrderItem::where('seller_id', $request->user()->id)->where('status', OrderStatus::Delivered)->count(),
            'cancelled' => OrderItem::where('seller_id', $request->user()->id)->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded])->count(),
        ];

        return Inertia::render('seller/orders/index', [
            'orders' => $orders,
            'counts' => $counts,
            'activeStatus' => $status ?: 'all',
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
        ]);
    }

    public function update(Request $request, OrderItem $orderItem): RedirectResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:processing,packed,shipped,delivered'],
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

        return back()->with('success', 'Order updated.');
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

        return back()->with('success', 'Order rejected. Refund credited to buyer wallet.');
    }
}
