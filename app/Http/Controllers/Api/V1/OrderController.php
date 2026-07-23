<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['items', 'seller.sellerProfile'])
            ->where('buyer_id', $request->user()->id)
            ->latest()
            ->paginate(min(50, max(1, (int) $request->get('per_page', 20))));

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $order) => $this->orderPayload($order))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        $order->load(['items.product.images', 'seller.sellerProfile']);

        return response()->json(['data' => $this->orderPayload($order)]);
    }

    public function confirmDelivery(Request $request, Order $order, OrderItem $orderItem): JsonResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);
        abort_unless($orderItem->order_id === $order->id, 404);

        try {
            $item = $this->orderService->confirmBuyerDelivery($orderItem, releaseFunds: true);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Delivery confirmed.',
            'item' => [
                'id' => $item->id,
                'status' => $item->status?->value,
                'funds_release_status' => $item->funds_release_status?->value,
            ],
        ]);
    }

    private function orderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status?->value,
            'payment_status' => $order->payment_status?->value,
            'payment_channel' => $order->payment_channel?->value,
            'payment_method' => $order->payment_method,
            'receiver_name' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'region' => $order->region,
            'city' => $order->city,
            'subtotal' => (float) $order->subtotal,
            'shipping_cost' => (float) $order->shipping_cost,
            'total' => (float) $order->total,
            'created_at' => $order->created_at?->toIso8601String(),
            'seller' => [
                'id' => $order->seller_id,
                'store_name' => $order->seller?->sellerProfile?->displayName() ?? $order->seller?->name,
            ],
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => $item->lineTotal(),
                'status' => $item->status?->value,
                'funds_release_status' => $item->funds_release_status?->value,
            ])->values(),
        ];
    }
}
