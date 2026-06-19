<?php

namespace App\Http\Controllers\Seller;

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
        $orders = OrderItem::with(['order.buyer', 'product'])
            ->where('seller_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return Inertia::render('seller/orders/index', [
            'orders' => $orders,
        ]);
    }

    public function update(Request $request, OrderItem $orderItem): RedirectResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:processing,packed,shipped,delivered'],
            'courier_name' => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
        ]);

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
