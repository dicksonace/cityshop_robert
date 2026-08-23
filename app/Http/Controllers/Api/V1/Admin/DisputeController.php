<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Wallet;
use App\Notifications\DisputeResolvedNotification;
use App\Services\AppNotificationService;
use App\Services\WalletService;
use App\Services\WalletTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status', 'open')->toString();
        $disputes = Dispute::with(['order:id,order_number', 'buyer:id,name,mobile', 'seller:id,name', 'orderItem'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $disputes->getCollection()->map(fn (Dispute $dispute) => $this->serialize($dispute))->values(),
            'meta' => [
                'current_page' => $disputes->currentPage(),
                'last_page' => $disputes->lastPage(),
                'total' => $disputes->total(),
            ],
            'status' => $status,
        ]);
    }

    public function review(Dispute $dispute): JsonResponse
    {
        if ($dispute->status !== DisputeStatus::Open) {
            return response()->json(['message' => 'Only open refunds can be marked under review.'], 422);
        }
        $dispute->update(['status' => DisputeStatus::UnderReview]);

        return response()->json([
            'message' => 'Marked as under review.',
            'data' => $this->serialize($dispute->fresh(['order', 'buyer', 'seller', 'orderItem'])),
        ]);
    }

    public function resolve(Request $request, Dispute $dispute): JsonResponse
    {
        $validated = $request->validate([
            'resolution' => ['required', 'in:resolved_buyer,resolved_seller,closed'],
            'resolution_notes' => ['required', 'string', 'max:2000'],
        ]);

        $dispute->update([
            'status' => DisputeStatus::from($validated['resolution']),
            'resolution_notes' => $validated['resolution_notes'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        $item = $dispute->orderItem;

        if ($validated['resolution'] === 'resolved_buyer') {
            $refundAmount = $item->lineTotal();
            WalletService::ensure($dispute->buyer)->increment('available_balance', $refundAmount);
            WalletTransactionService::recordOrderRefund($item, $refundAmount);

            $sellerWallet = Wallet::where('user_id', $item->seller_id)->first();
            if ($sellerWallet) {
                $sellerAmount = (float) $item->seller_amount;
                $sellerWallet->decrement('pending_balance', min($sellerAmount, (float) $sellerWallet->pending_balance));
                $sellerWallet->decrement('available_balance', min($sellerAmount, (float) $sellerWallet->available_balance));
                $sellerWallet->decrement('total_earnings', min($sellerAmount, (float) $sellerWallet->total_earnings));
            }
            WalletTransactionService::recordSaleReversed($item);
            $item->update(['status' => OrderStatus::Refunded]);
            $dispute->order->update(['payment_status' => PaymentStatus::Refunded]);
        }

        if ($validated['resolution'] === 'resolved_seller') {
            $wallet = Wallet::where('user_id', $item->seller_id)->first();
            if ($wallet && $item->status !== OrderStatus::Delivered) {
                $amount = (float) $item->seller_amount;
                $wallet->decrement('pending_balance', $amount);
                $wallet->increment('available_balance', $amount);
                $item->update(['status' => OrderStatus::Delivered]);
            }
        }

        $dispute->load('order');
        $dispute->buyer->notify(new DisputeResolvedNotification($dispute));
        $dispute->seller->notify(new DisputeResolvedNotification($dispute));
        if ($dispute->buyer) {
            AppNotificationService::notifyDisputeResolved($dispute->buyer, $dispute);
        }
        if ($dispute->seller) {
            AppNotificationService::notifyDisputeResolved($dispute->seller, $dispute);
        }

        return response()->json([
            'message' => 'Refund request resolved.',
            'data' => $this->serialize($dispute->fresh(['order', 'buyer', 'seller', 'orderItem'])),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Dispute $dispute): array
    {
        $item = $dispute->orderItem;
        $status = $dispute->status?->value ?? (string) $dispute->status;

        return [
            'id' => $dispute->id,
            'status' => $status,
            'status_label' => str($status)->replace('_', ' ')->title()->toString(),
            'reason' => $dispute->reason,
            'reason_label' => str($dispute->reason ?? '')->replace('_', ' ')->title()->toString(),
            'description' => $dispute->description,
            'resolution_notes' => $dispute->resolution_notes,
            'created_at' => $dispute->created_at?->toIso8601String(),
            'resolved_at' => $dispute->resolved_at?->toIso8601String(),
            'order_id' => $dispute->order_id,
            'order_item_id' => $dispute->order_item_id,
            'order_number' => $dispute->order?->order_number,
            'product_name' => $item?->product_name,
            'refund_amount' => $item ? (float) $item->lineTotal() : null,
            'buyer_name' => $dispute->buyer?->name,
            'buyer_mobile' => $dispute->buyer?->mobile,
            'seller_name' => $dispute->seller?->name,
            'seller_mobile' => $dispute->seller?->mobile,
            'funds_release_status' => $item?->funds_release_status?->value,
            'is_open' => $dispute->isOpen(),
        ];
    }
}
