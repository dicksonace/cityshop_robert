<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['buyer:id,name,mobile', 'items'])
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $order) => $this->serialize($order))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'buyer:id,name,email,mobile',
            'items.seller:id,name,mobile',
            'items.seller.sellerProfile',
            'items.product.images',
        ]);

        return response()->json(['data' => $this->serialize($order, detailed: true)]);
    }

    public function unprocessed(Request $request): JsonResponse
    {
        $hours = max(1, (int) $request->integer('hours', 24));
        $items = $this->orders->staleUnprocessedItemsQuery($hours)
            ->with(['order.buyer:id,name,mobile', 'seller:id,name'])
            ->paginate(20);

        return response()->json([
            'data' => $items->getCollection()->map(fn (OrderItem $item) => $this->serializeItem($item))->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
            'hours' => $hours,
        ]);
    }

    public function cancelUnprocessed(Request $request, OrderItem $orderItem): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $reason = $validated['reason'] ?? 'Admin cancelled: order does not look like it will go through.';
        if (! str_starts_with(mb_strtolower($reason), 'admin')) {
            $reason = 'Admin: '.$reason;
        }

        try {
            $this->orders->adminCancelUnprocessedItem($orderItem, $reason);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Order cancelled. Buyer refunded to wallet.']);
    }

    public function awaitingConfirmation(Request $request): JsonResponse
    {
        $items = OrderItem::query()
            ->where('status', OrderStatus::AwaitingConfirmation)
            ->with(['order.buyer:id,name,mobile', 'seller:id,name'])
            ->latest('updated_at')
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $items->getCollection()->map(fn (OrderItem $item) => $this->serializeItem($item))->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function confirmDelivery(OrderItem $orderItem): JsonResponse
    {
        try {
            $this->orders->confirmBuyerDelivery($orderItem, releaseFunds: false);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Delivery confirmed. Funds stay pending until you release them.']);
    }

    public function awaitingDirect(Request $request): JsonResponse
    {
        $claim = $request->string('claim')->toString();
        if (! in_array($claim, ['all', 'awaiting_claim', 'claimed'], true)) {
            $claim = 'all';
        }

        $orders = $this->orders->awaitingDirectPaymentOrdersQuery($claim)
            ->with(['buyer:id,name,mobile', 'seller.sellerProfile', 'items'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $orders->getCollection()->map(function (Order $order) {
                $hasClaim = filled($order->direct_payment_reference) || filled($order->direct_payment_proof_path);

                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => (float) $order->total,
                    'status' => $order->status?->value,
                    'payment_status' => $order->payment_status?->value,
                    'claim_status' => $hasClaim ? 'claimed' : 'awaiting_claim',
                    'direct_payment_reference' => $order->direct_payment_reference,
                    'buyer_name' => $order->buyer?->name,
                    'seller_name' => $order->seller?->sellerProfile?->store_name ?? $order->seller?->name,
                    'created_at' => $order->created_at?->toIso8601String(),
                ];
            })->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
            ],
            'claim' => $claim,
        ]);
    }

    public function cancellations(Request $request): JsonResponse
    {
        $items = OrderItem::query()
            ->with(['order.buyer:id,name', 'seller.sellerProfile'])
            ->where('status', OrderStatus::Cancelled)
            ->where('cancelled_by', \App\Support\OrderCancellation::BY_SELLER)
            ->latest('cancelled_at')
            ->paginate(20);

        return response()->json([
            'data' => $items->getCollection()->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'order_id' => $item->order_id,
                'product_name' => $item->product_name,
                'quantity' => (int) $item->quantity,
                'rejection_reason' => $item->rejection_reason,
                'cancellation_label' => \App\Support\OrderCancellation::label($item->cancellation_code),
                'cancelled_at' => $item->cancelled_at?->toIso8601String(),
                'order_number' => $item->order?->order_number,
                'buyer_name' => $item->order?->buyer?->name,
                'seller_name' => $item->seller?->sellerProfile?->store_name ?? $item->seller?->name,
            ])->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Order $order, bool $detailed = false): array
    {
        $payload = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status?->value ?? (string) $order->status,
            'payment_status' => $order->payment_status?->value ?? (string) $order->payment_status,
            'total' => (float) $order->total,
            'created_at' => $order->created_at?->toIso8601String(),
            'buyer' => $order->buyer ? [
                'id' => $order->buyer->id,
                'name' => $order->buyer->name,
                'email' => $order->buyer->email,
                'mobile' => $order->buyer->mobile,
            ] : null,
            'items_count' => $order->items->count(),
        ];

        if ($detailed) {
            $payload['payment_method'] = $order->payment_method;
            $payload['payment_channel'] = $order->payment_channel?->value;
            $payload['payment_reference'] = $order->payment_reference;
            $payload['shipping_cost'] = (float) $order->shipping_cost;
            $payload['receiver_name'] = $order->receiver_name;
            $payload['receiver_phone'] = $order->receiver_phone;
            $payload['city'] = $order->city;
            $payload['region'] = $order->region;
            $payload['digital_address'] = $order->digital_address;
            $payload['delivery_notes'] = $order->delivery_notes;
            $payload['items'] = $order->items->map(fn (OrderItem $item) => $this->serializeItem($item, detailed: true))->values();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(OrderItem $item, bool $detailed = false): array
    {
        $images = $item->product?->images;
        $imagePath = $images?->firstWhere('is_primary', true)?->path
            ?? $images?->first()?->path;
        $storeName = $item->seller?->sellerProfile?->displayName() ?? $item->seller?->name;

        $row = [
            'id' => $item->id,
            'order_id' => $item->order_id,
            'product_name' => $item->product_name,
            'product_image' => $this->publicUrl($imagePath),
            'quantity' => (int) $item->quantity,
            'unit_price' => (float) $item->unit_price,
            'seller_amount' => (float) $item->seller_amount,
            'status' => $item->status?->value ?? (string) $item->status,
            'funds_release_status' => $item->funds_release_status?->value,
            'order_number' => $item->order?->order_number,
            'buyer_name' => $item->order?->buyer?->name,
            'seller_name' => $storeName,
            'seller_mobile' => $item->seller?->mobile,
            'vehicle_number' => $item->vehicle_number,
            'driver_phone' => $item->driver_phone,
            'package_image_url' => $this->publicUrl($item->package_image),
        ];

        if ($detailed) {
            $row['rejection_reason'] = $item->rejection_reason;
            $row['courier_name'] = $item->courier_name;
            $row['tracking_number'] = $item->tracking_number;
        }

        return $row;
    }

    private function publicUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return str_starts_with($path, 'http')
            ? $path
            : Storage::disk('public')->url($path);
    }
}
