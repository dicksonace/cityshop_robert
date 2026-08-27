<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChinaTransferStatus;
use App\Http\Controllers\Controller;
use App\Models\ChinaTransfer;
use App\Models\ChinaTransferRate;
use App\Models\SellRmbRate;
use App\Models\Wallet;
use App\Models\WalletConversion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RmbOpsController extends Controller
{
    public function conversions(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $conversions = WalletConversion::query()
            ->with('user:id,name,email,mobile')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('reference', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('mobile', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (WalletConversion $c) => [
                'id' => $c->id,
                'reference' => $c->reference,
                'direction' => $c->direction,
                'amount_ghs' => (float) $c->amount_ghs,
                'amount_rmb' => (float) $c->amount_rmb,
                'rate' => (float) $c->rate,
                'status' => $c->status,
                'ip_address' => $c->ip_address,
                'created_at' => $c->created_at?->toIso8601String(),
                'user' => $c->user ? [
                    'id' => $c->user->id,
                    'name' => $c->user->name,
                    'mobile' => $c->user->mobile,
                ] : null,
            ]);

        return Inertia::render('admin/rmb-ops/conversions', [
            'conversions' => $conversions,
            'search' => $search !== '' ? $search : null,
        ]);
    }

    public function reconciliation(): Response
    {
        $totalRmbBalances = (float) Wallet::query()->sum('rmb_balance');
        $totalGhsBalances = (float) Wallet::query()->sum('available_balance');

        $openHolds = ChinaTransfer::query()
            ->where('funding_source', 'rmb_wallet')
            ->where('rmb_wallet_refunded', false)
            ->whereIn('status', [
                ChinaTransferStatus::PaymentVerification,
                ChinaTransferStatus::Processing,
                ChinaTransferStatus::RmbSent,
            ]);

        $heldRmb = (float) (clone $openHolds)->sum('rmb_amount');
        $openCount = (clone $openHolds)->count();

        $todayConverts = WalletConversion::query()->whereDate('created_at', today())->count();
        $todayRmbOut = (float) ChinaTransfer::query()
            ->where('funding_source', 'rmb_wallet')
            ->whereDate('created_at', today())
            ->whereNotIn('status', [
                ChinaTransferStatus::Cancelled,
                ChinaTransferStatus::PaymentRejected,
                ChinaTransferStatus::TransferFailed,
            ])
            ->sum('rmb_amount');

        $openTransfers = (clone $openHolds)
            ->with('user:id,name,mobile')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (ChinaTransfer $t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'status' => $t->status->value,
                'status_label' => $t->status->label(),
                'rmb_amount' => (float) $t->rmb_amount,
                'needs_approval' => (bool) $t->needs_approval,
                'user' => $t->user?->name,
                'created_at' => $t->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/rmb-ops/reconciliation', [
            'summary' => [
                'total_rmb_balances' => $totalRmbBalances,
                'total_ghs_available' => $totalGhsBalances,
                'open_rmb_holds' => $heldRmb,
                'open_hold_count' => $openCount,
                'float_check' => round($totalRmbBalances + $heldRmb, 2),
                'today_converts' => $todayConverts,
                'today_rmb_out' => $todayRmbOut,
            ],
            'openTransfers' => $openTransfers,
        ]);
    }

    public function rateHistory(): Response
    {
        $buyRates = ChinaTransferRate::query()
            ->with('creator:id,name')
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(fn (ChinaTransferRate $r) => [
                'id' => $r->id,
                'side' => 'buy',
                'ghs_per_rmb' => (float) $r->ghs_per_rmb,
                'active' => (bool) $r->active,
                'effective_from' => $r->effective_from?->toIso8601String(),
                'effective_to' => $r->effective_to?->toIso8601String(),
                'created_by' => $r->creator?->name,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        $sellRates = SellRmbRate::query()
            ->with('creator:id,name')
            ->latest('id')
            ->limit(40)
            ->get()
            ->map(fn (SellRmbRate $r) => [
                'id' => $r->id,
                'side' => 'sell',
                'ghs_per_rmb' => $r->ghsPerRmb(),
                'usd_per_rmb' => (float) $r->usd_per_rmb,
                'ghs_per_usd' => (float) $r->ghs_per_usd,
                'active' => (bool) $r->active,
                'effective_from' => $r->effective_from?->toIso8601String(),
                'effective_to' => $r->effective_to?->toIso8601String(),
                'created_by' => $r->creator?->name,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/rmb-ops/rate-history', [
            'buyRates' => $buyRates,
            'sellRates' => $sellRates,
        ]);
    }
}
