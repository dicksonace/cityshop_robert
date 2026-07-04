<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Wallet;
use App\Notifications\DisputeResolvedNotification;
use App\Services\WalletService;
use App\Services\WalletTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DisputeController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', 'open');

        $disputes = Dispute::with(['order', 'buyer', 'seller', 'orderItem'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/disputes/index', [
            'disputes' => $disputes,
            'status' => $status,
        ]);
    }

    public function review(Request $request, Dispute $dispute): RedirectResponse
    {
        abort_unless($dispute->status === DisputeStatus::Open, 422);

        $dispute->update(['status' => DisputeStatus::UnderReview]);

        return back()->with('success', 'Refund request marked as under review.');
    }

    public function resolve(Request $request, Dispute $dispute): RedirectResponse
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
            $buyerWallet = WalletService::ensure($dispute->buyer);
            $buyerWallet->increment('available_balance', $refundAmount);
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

        return back()->with('success', 'Refund request resolved.');
    }
}
