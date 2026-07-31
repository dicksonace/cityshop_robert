<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Support\BuyerOrderPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['items.product.images', 'items.dispute', 'seller.sellerProfile'])
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

        $order->load(['items.product.images', 'items.dispute', 'seller.sellerProfile', 'sellerPaymentMethod']);

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
        $order->loadMissing(['items.product.images', 'items.dispute', 'seller.sellerProfile', 'sellerPaymentMethod']);
        $method = $order->sellerPaymentMethod;
        $canRequestRefund = BuyerOrderPolicy::canRequestRefund($order);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status?->value,
            'payment_status' => $order->payment_status?->value,
            'payment_channel' => $order->payment_channel?->value,
            'payment_method' => $order->payment_method,
            'direct_payment_reference' => $order->direct_payment_reference,
            'direct_payment_proof_path' => $order->direct_payment_proof_path,
            'direct_payment_submitted_at' => $order->direct_payment_submitted_at?->toIso8601String(),
            'direct_payment_rejection_reason' => $order->direct_payment_rejection_reason,
            'receiver_name' => $order->receiver_name,
            'receiver_phone' => $order->receiver_phone,
            'region' => $order->region,
            'city' => $order->city,
            'digital_address' => $order->digital_address,
            'delivery_notes' => $order->delivery_notes,
            'subtotal' => (float) $order->subtotal,
            'shipping_cost' => (float) $order->shipping_cost,
            'total' => (float) $order->total,
            'created_at' => $order->created_at?->toIso8601String(),
            'can_request_refund' => $canRequestRefund,
            'seller' => [
                'id' => $order->seller_id,
                'store_name' => $order->seller?->sellerProfile?->displayName() ?? $order->seller?->name,
                'store_slug' => $order->seller?->sellerProfile?->slug,
            ],
            'seller_payment_method' => $method ? [
                'id' => $method->id,
                'type' => $method->type->value,
                'label' => $method->label,
                'account_name' => $method->account_name,
                'account_number' => $method->account_number,
                'network' => $method->network,
                'bank_name' => $method->bank_name,
                'instructions' => $method->instructions,
                'display_label' => $method->displayLabel(),
            ] : null,
            'items' => $order->items->map(function ($item) use ($canRequestRefund) {
                $image = $item->product?->images?->sortByDesc('is_primary')->first()
                    ?? $item->product?->images?->first();
                $path = $image?->path;
                $imageUrl = null;
                if (is_string($path) && $path !== '') {
                    $imageUrl = str_starts_with($path, 'http')
                        ? $path
                        : Storage::disk('public')->url($path);
                }

                $dispute = $item->dispute;
                $status = $item->status?->value;
                $disputeStatus = $dispute?->status?->value;
                $canItemRefund = $canRequestRefund
                    && in_array($status, ['shipped', 'awaiting_confirmation', 'delivered'], true)
                    && (! $dispute || $disputeStatus === 'cancelled');

                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => (float) $item->lineTotal(),
                    'status' => $status,
                    'funds_release_status' => $item->funds_release_status?->value,
                    'image_url' => $imageUrl,
                    'can_request_refund' => $canItemRefund,
                    'dispute' => $dispute && $disputeStatus !== 'cancelled' ? [
                        'id' => $dispute->id,
                        'status' => $disputeStatus,
                        'reason' => $dispute->reason,
                        'description' => $dispute->description,
                    ] : null,
                ];
            })->values(),
        ];
    }
}
