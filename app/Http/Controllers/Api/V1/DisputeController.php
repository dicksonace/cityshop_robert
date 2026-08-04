<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DisputeStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\DisputeOpenedNotification;
use App\Services\AppNotificationService;
use App\Support\BuyerOrderPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class DisputeController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->buyer_id === $request->user()->id, 403);

        $validated = $request->validate([
            'order_item_id' => ['required', 'exists:order_items,id'],
            'reason' => ['required', 'in:wrong_item,damaged_item,not_delivered,other'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $item = OrderItem::where('id', $validated['order_item_id'])
            ->where('order_id', $order->id)
            ->firstOrFail();

        if (! BuyerOrderPolicy::canRequestRefund($order)) {
            return response()->json([
                'message' => 'Refund requests are only available for '.BuyerOrderPolicy::months().' months after the order date. This order has expired.',
            ], 422);
        }

        if (! in_array($item->status->value, ['shipped', 'awaiting_confirmation', 'delivered'], true)) {
            return response()->json([
                'message' => 'Refunds can only be requested for items that are out for delivery or delivered.',
            ], 422);
        }

        if (Dispute::where('order_item_id', $item->id)->whereNotIn('status', [DisputeStatus::Cancelled])->exists()) {
            return response()->json([
                'message' => 'This item already has a refund or dispute on record.',
            ], 422);
        }

        $dispute = Dispute::create([
            'order_id' => $order->id,
            'order_item_id' => $item->id,
            'buyer_id' => $request->user()->id,
            'seller_id' => $item->seller_id,
            'reason' => $validated['reason'],
            'description' => $validated['description'],
            'status' => DisputeStatus::Open,
        ]);

        $dispute->load('order');

        if ($item->seller) {
            $item->seller->notify(new DisputeOpenedNotification($dispute));
            AppNotificationService::send(
                $item->seller,
                'dispute',
                'Refund request opened',
                "Buyer requested a refund on order {$order->order_number} ({$item->product_name}).",
                [
                    'dispute_id' => $dispute->id,
                    'order_id' => $order->id,
                ],
            );
        }

        $request->user()->notify(new DisputeOpenedNotification($dispute));
        AppNotificationService::send(
            $request->user(),
            'dispute',
            'Refund request submitted',
            "We received your refund request for {$item->product_name}.",
            [
                'dispute_id' => $dispute->id,
                'order_id' => $order->id,
                'url' => $order->checkout_id
                    ? route('checkouts.show', $order->checkout_id)
                    : route('orders.show', $order->id),
            ],
        );

        $admins = User::where('role', UserRole::Admin)->get();
        Notification::send($admins, new DisputeOpenedNotification($dispute));

        return response()->json([
            'message' => 'Refund request submitted. Admin will review before any refund is issued.',
            'dispute' => $this->disputePayload($dispute),
        ], 201);
    }

    public function cancel(Request $request, Dispute $dispute): JsonResponse
    {
        abort_unless($dispute->buyer_id === $request->user()->id, 403);

        if (! in_array($dispute->status, [DisputeStatus::Open, DisputeStatus::UnderReview], true)) {
            return response()->json([
                'message' => 'This refund request can no longer be cancelled.',
            ], 422);
        }

        $dispute->update([
            'status' => DisputeStatus::Cancelled,
            'resolution_notes' => 'Cancelled by buyer.',
            'resolved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Refund request cancelled.',
            'dispute' => $this->disputePayload($dispute->fresh()),
        ]);
    }

    private function disputePayload(Dispute $dispute): array
    {
        return [
            'id' => $dispute->id,
            'order_id' => $dispute->order_id,
            'order_item_id' => $dispute->order_item_id,
            'reason' => $dispute->reason,
            'description' => $dispute->description,
            'status' => $dispute->status?->value ?? (string) $dispute->status,
            'created_at' => $dispute->created_at?->toIso8601String(),
        ];
    }
}
