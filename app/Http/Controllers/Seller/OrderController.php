<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\SellerOrderPrintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

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

    public function index(Request $request): InertiaResponse|RedirectResponse
    {
        if ($request->filled('status')) {
            $stage = $this->legacyStatusToStage($request->string('status')->toString());

            return redirect()->route('seller.orders.stage', $stage);
        }

        return $this->hub($request);
    }

    public function hub(Request $request): InertiaResponse
    {
        $sellerId = $request->user()->id;
        $counts = $this->orderCounts($sellerId);

        $urgent = OrderItem::with(['order.buyer', 'product.images'])
            ->where('seller_id', $sellerId)
            ->visibleToSeller()
            ->whereIn('status', [
                OrderStatus::Pending,
                OrderStatus::Processing,
                OrderStatus::CallConfirmed,
                OrderStatus::Packed,
                OrderStatus::Shipped,
            ])
            ->latest()
            ->limit(6)
            ->get();

        $recentCompleted = OrderItem::with(['order.buyer', 'product.images'])
            ->where('seller_id', $sellerId)
            ->visibleToSeller()
            ->where('status', OrderStatus::Delivered)
            ->latest()
            ->limit(4)
            ->get();

        return Inertia::render('seller/orders/hub', [
            'counts' => $counts,
            'urgentOrders' => $urgent,
            'recentCompleted' => $recentCompleted,
            'needsAction' => $counts['pending'] + $counts['processing'] + $counts['call_confirmed'] + $counts['packed'] + $counts['shipped'],
        ]);
    }

    public function stage(Request $request, string $stage): InertiaResponse|RedirectResponse
    {
        if (! array_key_exists($stage, self::STAGE_MAP)) {
            return redirect()->route('seller.orders.index');
        }

        $sellerId = $request->user()->id;
        $config = self::STAGE_MAP[$stage];

        $query = OrderItem::with(['order.buyer', 'product.images'])
            ->where('seller_id', $sellerId)
            ->visibleToSeller();

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

    public function show(Request $request, OrderItem $orderItem): InertiaResponse|RedirectResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);

        $orderItem->load([
            'order.buyer',
            'product.images',
            'dispute',
        ]);

        abort_if($orderItem->order === null, 404);

        if (! $this->isVisibleOnSellerDashboard($orderItem)) {
            return redirect()
                ->route('seller.orders.index')
                ->with('error', 'This Pay-to-seller order will appear after the buyer submits payment.');
        }

        return Inertia::render('seller/orders/show', [
            'orderItem' => $this->serializeOrderItem($orderItem),
            'backStage' => $this->statusToStage($orderItem->status),
            'cancellationReasons' => \App\Support\OrderCancellation::reasons(),
            'canCancel' => in_array(
                $orderItem->status->value,
                \App\Support\OrderCancellation::sellerCancellableStatuses(),
                true
            ),
        ]);
    }

    public function update(Request $request, OrderItem $orderItem): RedirectResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:processing,call_confirmed,packed,shipped,awaiting_confirmation,delivered'],
            'vehicle_number' => ['nullable', 'string', 'max:50'],
            'driver_phone' => ['nullable', 'string', 'max:30'],
            'package_image' => ['nullable', 'image', 'max:5120'],
        ]);

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

        $reasons = array_keys(\App\Support\OrderCancellation::reasons());

        $validated = $request->validate([
            'cancellation_code' => ['required', 'string', 'in:'.implode(',', $reasons)],
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $code = $validated['cancellation_code'];
        $label = \App\Support\OrderCancellation::label($code);
        $custom = trim((string) ($validated['rejection_reason'] ?? ''));

        if ($code === 'other' && $custom === '') {
            return back()->withErrors(['rejection_reason' => 'Please explain why you are cancelling.'])->withInput();
        }

        $reason = $code === 'other' ? $custom : ($custom !== '' ? "{$label}: {$custom}" : $label);

        try {
            $this->orderService->rejectOrderItem(
                $orderItem,
                $reason,
                $code,
                \App\Support\OrderCancellation::BY_SELLER,
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = $orderItem->fresh()->refund_status === \App\Support\OrderCancellation::REFUND_COMPLETED
            ? 'Order cancelled. The buyer was refunded to their CityShop wallet (debited from your available balance).'
            : 'Order cancelled.';

        return redirect()->route('seller.orders.stage', 'cancelled')
            ->with('success', $msg);
    }

    public function print(Request $request, OrderItem $orderItem, SellerOrderPrintService $printService): Response|RedirectResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);
        $orderItem->loadMissing('order');

        if (! $this->isVisibleOnSellerDashboard($orderItem)) {
            return redirect()
                ->route('seller.orders.index')
                ->with('error', 'This Pay-to-seller order will appear after the buyer submits payment.');
        }

        // Same mPDF output as download — print/open matches download size.
        return $printService->stream($orderItem, $request->user());
    }

    public function pdf(Request $request, OrderItem $orderItem, SellerOrderPrintService $printService): Response|RedirectResponse
    {
        abort_unless($orderItem->seller_id === $request->user()->id, 403);
        $orderItem->loadMissing('order');

        if (! $this->isVisibleOnSellerDashboard($orderItem)) {
            return redirect()
                ->route('seller.orders.index')
                ->with('error', 'This Pay-to-seller order will appear after the buyer submits payment.');
        }

        return $printService->pdf($orderItem, $request->user());
    }

    private function isVisibleOnSellerDashboard(OrderItem $orderItem): bool
    {
        $order = $orderItem->order;
        if (! $order) {
            return false;
        }

        if ($order->payment_channel !== \App\Enums\PaymentChannel::Direct) {
            return true;
        }

        if ($order->payment_status !== \App\Enums\PaymentStatus::Pending) {
            return true;
        }

        return filled($order->direct_payment_reference) || filled($order->direct_payment_proof_path);
    }

    /**
     * @return array<string, int>
     */
    private function orderCounts(int $sellerId): array
    {
        $base = OrderItem::where('seller_id', $sellerId)->visibleToSeller();

        return [
            'all' => (clone $base)->count(),
            'pending' => (clone $base)->where('status', OrderStatus::Pending)->count(),
            'processing' => (clone $base)->where('status', OrderStatus::Processing)->count(),
            'call_confirmed' => (clone $base)->where('status', OrderStatus::CallConfirmed)->count(),
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
            'call_confirmed' => 'call',
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
            OrderStatus::CallConfirmed => 'call',
            OrderStatus::Packed => 'packing',
            OrderStatus::Shipped => 'delivery',
            OrderStatus::AwaitingConfirmation => 'awaiting',
            OrderStatus::Delivered => 'completed',
            OrderStatus::Cancelled, OrderStatus::Refunded => 'cancelled',
            default => 'new',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderItem(OrderItem $orderItem): array
    {
        $order = $orderItem->order;

        return [
            'id' => $orderItem->id,
            'product_name' => $orderItem->product_name,
            'quantity' => $orderItem->quantity,
            'unit_price' => (float) $orderItem->unit_price,
            'seller_amount' => (float) $orderItem->seller_amount,
            'status' => $orderItem->status->value,
            'funds_release_status' => $orderItem->funds_release_status?->value,
            'funds_release_notes' => $orderItem->funds_release_notes,
            'rejection_reason' => $orderItem->rejection_reason,
            'cancellation_code' => $orderItem->cancellation_code,
            'cancelled_by' => $orderItem->cancelled_by,
            'cancelled_at' => $orderItem->cancelled_at?->toIso8601String(),
            'refund_status' => $orderItem->refund_status,
            'vehicle_number' => $orderItem->vehicle_number,
            'driver_phone' => $orderItem->driver_phone,
            'package_image' => $orderItem->package_image,
            'product' => $orderItem->product ? [
                'images' => $orderItem->product->images->map(fn ($image) => [
                    'path' => $image->path,
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
                'direct_payment_proof_path' => $order->direct_payment_proof_path,
                'direct_payment_rejection_reason' => $order->direct_payment_rejection_reason,
                'receiver_name' => $order->receiver_name,
                'receiver_phone' => $order->receiver_phone,
                'city' => $order->city,
                'region' => $order->region,
                'delivery_notes' => $order->delivery_notes,
                'buyer' => $order->buyer ? [
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
}
