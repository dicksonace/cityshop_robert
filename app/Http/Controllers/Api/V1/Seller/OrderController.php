<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\SellerOrderPrintService;
use App\Support\OrderCancellation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    private const STAGE_MAP = [
        'new' => ['status' => OrderStatus::Pending],
        'processing' => ['status' => OrderStatus::Processing],
        'call' => ['status' => OrderStatus::CallConfirmed],
        'packing' => ['status' => OrderStatus::Packed],
        'delivery' => ['status' => OrderStatus::Shipped],
        'awaiting' => ['status' => OrderStatus::AwaitingConfirmation],
        'completed' => ['status' => OrderStatus::Delivered],
        'cancelled' => ['statuses' => [OrderStatus::Cancelled, OrderStatus::Refunded]],
        'all' => [],
    ];

    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $sellerId = $request->user()->id;
        $stage = $request->string('stage', 'all')->toString();
        if (! array_key_exists($stage, self::STAGE_MAP)) {
            $stage = 'all';
        }

        $config = self::STAGE_MAP[$stage];
        $query = OrderItem::with(['order.buyer', 'product.images'])
            ->where('seller_id', $sellerId)
            ->visibleToSeller();

        if (isset($config['status'])) {
            $query->where('status', $config['status']);
            if ($stage === 'call') {
                $query->whereHas('order', fn ($q) => $q->where('payment_method', 'cash'));
            }
        } elseif (isset($config['statuses'])) {
            $query->whereIn('status', $config['statuses']);
        }

        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);
        $orders = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => $orders->getCollection()->map(fn (OrderItem $item) => $this->serializeListItem($item))->values(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'stage' => $stage,
            ],
            'counts' => $this->orderCounts($sellerId),
            'stages' => $this->stageCatalog(),
        ]);
    }

    public function show(Request $request, OrderItem $orderItem): JsonResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);

        $orderItem->load(['order.buyer', 'product.images', 'dispute']);
        abort_if($orderItem->order === null, 404);

        if (! $this->isVisibleOnSellerDashboard($orderItem)) {
            return response()->json([
                'message' => 'This Pay-to-seller order will appear after the buyer submits payment.',
            ], 404);
        }

        return response()->json([
            'data' => $this->serializeOrderItem($orderItem),
        ]);
    }

    public function pdf(Request $request, OrderItem $orderItem, SellerOrderPrintService $printService): Response
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);
        $orderItem->loadMissing('order');
        abort_if($orderItem->order === null, 404);

        if (! $this->isVisibleOnSellerDashboard($orderItem)) {
            abort(404, 'This Pay-to-seller order will appear after the buyer submits payment.');
        }

        return $printService->pdf($orderItem, $request->user());
    }

    public function update(Request $request, OrderItem $orderItem): JsonResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,processing,call_confirmed,packed,shipped,awaiting_confirmation,delivered'],
            'vehicle_number' => ['nullable', 'string', 'max:50'],
            'driver_phone' => ['nullable', 'string', 'max:30'],
            'package_image' => ['nullable', 'image', 'max:5120'],
        ]);

        if (! filled($validated['status'] ?? null)) {
            unset($validated['status']);
        }

        foreach (['vehicle_number', 'driver_phone'] as $field) {
            if (array_key_exists($field, $validated)) {
                $trimmed = trim((string) $validated[$field]);
                $validated[$field] = $trimmed === '' ? null : $trimmed;
            }
        }

        if ($request->hasFile('package_image')) {
            $validated['package_image'] = $request->file('package_image')->store('order-packages', 'public');
        } else {
            unset($validated['package_image']);
        }

        $previousStatus = $orderItem->status->value;
        $requestedStatus = $validated['status'] ?? null;
        $statusChanging = is_string($requestedStatus) && $requestedStatus !== '' && $requestedStatus !== $previousStatus;

        try {
            $this->orderService->updateOrderItemStatus($orderItem, $validated);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $orderItem = $orderItem->fresh(['order.buyer', 'product.images', 'dispute']);

        $message = $statusChanging
            ? 'Order moved to the next stage.'
            : 'Delivery details saved. The buyer can see them on the order.';

        return response()->json([
            'message' => $message,
            'data' => $this->serializeOrderItem($orderItem),
        ]);
    }

    public function reject(Request $request, OrderItem $orderItem): JsonResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);

        $reasons = array_keys(OrderCancellation::reasons());
        $validated = $request->validate([
            'cancellation_code' => ['required', 'string', 'in:'.implode(',', $reasons)],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $code = $validated['cancellation_code'];
        $label = OrderCancellation::label($code);
        $custom = trim((string) ($validated['rejection_reason'] ?? ''));

        if ($code === 'other' && $custom === '') {
            return response()->json([
                'message' => 'Please explain why you are cancelling.',
                'errors' => ['rejection_reason' => ['Please explain why you are cancelling.']],
            ], 422);
        }

        $reason = $code === 'other' ? $custom : ($custom !== '' ? "{$label}: {$custom}" : $label);

        try {
            $this->orderService->rejectOrderItem(
                $orderItem,
                $reason,
                $code,
                OrderCancellation::BY_SELLER,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $orderItem = $orderItem->fresh(['order.buyer', 'product.images', 'dispute']);
        $refunded = $orderItem->refund_status === OrderCancellation::REFUND_COMPLETED;
        $message = $refunded
            ? 'Order cancelled. The buyer was refunded to their CityShop wallet (debited from your available balance).'
            : 'Order cancelled.';

        return response()->json([
            'message' => $message,
            'data' => $this->serializeOrderItem($orderItem),
        ]);
    }

    public function confirmDirectPayment(Request $request, OrderItem $orderItem): JsonResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);
        $order = $orderItem->order;
        abort_if($order === null, 404);
        abort_unless($order->payment_channel === PaymentChannel::Direct, 422);
        abort_unless($order->payment_status === PaymentStatus::Pending, 422);

        $this->orderService->confirmDirectPayment($order, $request->user());

        $orderItem = $orderItem->fresh(['order.buyer', 'product.images', 'dispute']);

        return response()->json([
            'message' => 'Customer manual payment confirmed. You can now process the order.',
            'data' => $this->serializeOrderItem($orderItem),
        ]);
    }

    public function rejectDirectPayment(Request $request, OrderItem $orderItem): JsonResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);
        $order = $orderItem->order;
        abort_if($order === null, 404);
        abort_unless($order->payment_channel === PaymentChannel::Direct, 422);
        abort_unless($order->payment_status === PaymentStatus::Pending, 422);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $this->orderService->rejectDirectPayment($order, $request->user(), $validated['reason']);

        $orderItem = $orderItem->fresh(['order.buyer', 'product.images', 'dispute']);

        return response()->json([
            'message' => 'Payment claim rejected. The buyer can submit a new payment reference.',
            'data' => $this->serializeOrderItem($orderItem),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function orderCounts(int $sellerId): array
    {
        $base = OrderItem::where('seller_id', $sellerId)->visibleToSeller();

        return [
            'all' => (clone $base)->count(),
            'new' => (clone $base)->where('status', OrderStatus::Pending)->count(),
            'processing' => (clone $base)->where('status', OrderStatus::Processing)->count(),
            'call' => (clone $base)->where('status', OrderStatus::CallConfirmed)
                ->whereHas('order', fn ($q) => $q->where('payment_method', 'cash'))
                ->count(),
            'packing' => (clone $base)->where('status', OrderStatus::Packed)->count(),
            'delivery' => (clone $base)->where('status', OrderStatus::Shipped)->count(),
            'awaiting' => (clone $base)->where('status', OrderStatus::AwaitingConfirmation)->count(),
            'completed' => (clone $base)->where('status', OrderStatus::Delivered)->count(),
            'cancelled' => (clone $base)->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded])->count(),
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    private function stageCatalog(): array
    {
        return [
            ['key' => 'all', 'label' => 'All'],
            ['key' => 'new', 'label' => 'New'],
            ['key' => 'processing', 'label' => 'Processing'],
            ['key' => 'call', 'label' => 'Call buyer'],
            ['key' => 'packing', 'label' => 'Packing'],
            ['key' => 'delivery', 'label' => 'Delivery'],
            ['key' => 'awaiting', 'label' => 'Awaiting'],
            ['key' => 'completed', 'label' => 'Completed'],
            ['key' => 'cancelled', 'label' => 'Cancelled'],
        ];
    }

    private function isVisibleOnSellerDashboard(OrderItem $orderItem): bool
    {
        $order = $orderItem->order;
        if (! $order) {
            return false;
        }

        if ($order->payment_channel !== PaymentChannel::Direct) {
            return true;
        }

        if ($order->payment_status !== PaymentStatus::Pending) {
            return true;
        }

        return filled($order->direct_payment_reference) || filled($order->direct_payment_proof_path);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListItem(OrderItem $item): array
    {
        $imagePath = $item->product?->images
            ->firstWhere('is_primary', true)?->path
            ?? $item->product?->images->first()?->path;
        $amount = (float) ($item->seller_amount ?: $item->unit_price * $item->quantity);

        return [
            'id' => $item->id,
            'order_id' => $item->order_id,
            'order_number' => $item->order?->order_number,
            'product_name' => $item->product_name ?: $item->product?->name,
            'product_image' => $this->publicUrl($imagePath),
            'buyer_name' => $item->order?->buyer?->name,
            'status' => $item->status?->value ?? (string) $item->status,
            'stage' => $this->statusToStage($item->status),
            'quantity' => (int) $item->quantity,
            'amount' => round($amount, 2),
            'payment_method' => $item->order?->payment_method,
            'payment_channel' => $item->order?->payment_channel?->value,
            'payment_status' => $item->order?->payment_status?->value,
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderItem(OrderItem $orderItem): array
    {
        $order = $orderItem->order;
        $nextActions = $this->nextActions($orderItem);
        $previousAction = $this->previousAction($orderItem);
        $canCancel = in_array(
            $orderItem->status->value,
            OrderCancellation::sellerCancellableStatuses(),
            true
        );

        $pendingDirect = $order
            && $order->payment_channel === PaymentChannel::Direct
            && $order->payment_status === PaymentStatus::Pending;

        return [
            'id' => $orderItem->id,
            'product_name' => $orderItem->product_name,
            'quantity' => (int) $orderItem->quantity,
            'unit_price' => (float) $orderItem->unit_price,
            'seller_amount' => (float) $orderItem->seller_amount,
            'status' => $orderItem->status->value,
            'stage' => $this->statusToStage($orderItem->status),
            'funds_release_status' => $orderItem->funds_release_status?->value,
            'funds_release_notes' => $orderItem->funds_release_notes,
            'rejection_reason' => $orderItem->rejection_reason,
            'cancellation_code' => $orderItem->cancellation_code,
            'cancelled_by' => $orderItem->cancelled_by,
            'cancelled_at' => $orderItem->cancelled_at?->toIso8601String(),
            'refund_status' => $orderItem->refund_status,
            'vehicle_number' => $orderItem->vehicle_number,
            'driver_phone' => $orderItem->driver_phone,
            'package_image' => $this->publicUrl($orderItem->package_image),
            'next_actions' => $nextActions,
            'previous_action' => $previousAction,
            'can_cancel' => $canCancel,
            'cancellation_reasons' => collect(OrderCancellation::reasons())
                ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])
                ->values()
                ->all(),
            'can_confirm_direct_payment' => $pendingDirect,
            'can_reject_direct_payment' => $pendingDirect,
            'product' => $orderItem->product ? [
                'id' => $orderItem->product->id,
                'name' => $orderItem->product->name,
                'images' => $orderItem->product->images->map(fn ($image) => [
                    'url' => $this->publicUrl($image->path),
                ])->values()->all(),
            ] : null,
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'created_at' => $order->created_at?->toIso8601String(),
                'payment_status' => $order->payment_status?->value ?? 'pending',
                'payment_method' => $order->payment_method,
                'payment_channel' => $order->payment_channel?->value,
                'direct_payment_reference' => $order->direct_payment_reference,
                'direct_payment_proof_url' => $this->publicUrl($order->direct_payment_proof_path),
                'direct_payment_rejection_reason' => $order->direct_payment_rejection_reason,
                'receiver_name' => $order->receiver_name,
                'receiver_phone' => $order->receiver_phone,
                'city' => $order->city,
                'region' => $order->region,
                'delivery_notes' => $order->delivery_notes,
                'buyer' => $order->buyer ? [
                    'id' => $order->buyer->id,
                    'name' => $order->buyer->name,
                    'email' => $order->buyer->email,
                    'mobile' => $order->buyer->mobile,
                ] : null,
            ],
            'dispute' => $orderItem->dispute ? [
                'id' => $orderItem->dispute->id,
                'reason' => $orderItem->dispute->reason,
                'description' => $orderItem->dispute->description,
                'status' => $orderItem->dispute->status->value,
                'resolution_notes' => $orderItem->dispute->resolution_notes,
            ] : null,
        ];
    }

    /**
     * @return list<array{status: string, label: string, needs_delivery_details?: bool}>
     */
    private function nextActions(OrderItem $item): array
    {
        $item->loadMissing('order');
        $isCod = $item->order?->payment_method === 'cash';
        $current = $item->status;

        $next = match (true) {
            $current === OrderStatus::Pending => OrderStatus::Processing,
            $current === OrderStatus::Processing && $isCod => OrderStatus::CallConfirmed,
            $current === OrderStatus::Processing => OrderStatus::Packed,
            $current === OrderStatus::CallConfirmed => OrderStatus::Packed,
            $current === OrderStatus::Packed => OrderStatus::Shipped,
            $current === OrderStatus::Shipped && $isCod => OrderStatus::Delivered,
            $current === OrderStatus::Shipped => OrderStatus::AwaitingConfirmation,
            default => null,
        };

        if (! $next) {
            return [];
        }

        return [[
            'status' => $next->value,
            'label' => $this->statusActionLabel($next->value),
            'needs_delivery_details' => $next === OrderStatus::Shipped,
        ]];
    }

    /**
     * @return array{status: string, label: string}|null
     */
    private function previousAction(OrderItem $item): ?array
    {
        $item->loadMissing('order');
        $isCod = $item->order?->payment_method === 'cash';
        $current = $item->status;

        $previous = match (true) {
            $current === OrderStatus::Processing => OrderStatus::Pending,
            $current === OrderStatus::CallConfirmed => OrderStatus::Processing,
            $current === OrderStatus::Packed && $isCod => OrderStatus::CallConfirmed,
            $current === OrderStatus::Packed => OrderStatus::Processing,
            $current === OrderStatus::Shipped => OrderStatus::Packed,
            $current === OrderStatus::AwaitingConfirmation => OrderStatus::Shipped,
            $current === OrderStatus::Delivered && $isCod => OrderStatus::Shipped,
            default => null,
        };

        if (! $previous) {
            return null;
        }

        return [
            'status' => $previous->value,
            'label' => 'Back to '.$this->statusActionLabel($previous->value),
        ];
    }

    private function statusActionLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'New / pending',
            'processing' => 'Start processing',
            'call_confirmed' => 'Confirm call with buyer',
            'packed' => 'Mark as packing',
            'shipped' => 'Out for delivery',
            'awaiting_confirmation' => 'Mark as delivered',
            'delivered' => 'Mark delivered',
            default => $status,
        };
    }

    private function statusToStage(?OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Pending => 'new',
            OrderStatus::Processing => 'processing',
            OrderStatus::CallConfirmed => 'call',
            OrderStatus::Packed => 'packing',
            OrderStatus::Shipped => 'delivery',
            OrderStatus::AwaitingConfirmation => 'awaiting',
            OrderStatus::Delivered => 'completed',
            OrderStatus::Cancelled, OrderStatus::Refunded => 'cancelled',
            default => 'new',
        };
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
