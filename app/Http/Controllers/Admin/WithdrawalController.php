<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WithdrawalPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function __construct(
        private WithdrawalPayoutService $payouts,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->get('status', 'pending');
        $role = $request->get('role', 'all');

        $withdrawals = Withdrawal::with(['user.wallet', 'user.sellerProfile'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($role === 'seller', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('role', UserRole::Seller)))
            ->when($role === 'buyer', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('role', UserRole::Buyer)))
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(function (Withdrawal $withdrawal) {
                $user = $withdrawal->user;
                $profile = $user?->sellerProfile;
                $wallet = $user?->wallet;

                return [
                    'id' => $withdrawal->id,
                    'amount' => (float) $withdrawal->amount,
                    'momo_number' => $withdrawal->momo_number,
                    'account_name' => $withdrawal->account_name,
                    'network' => $withdrawal->network,
                    'status' => $withdrawal->status->value,
                    'payout_channel' => $withdrawal->payout_channel,
                    'rejection_reason' => $withdrawal->rejection_reason,
                    'failure_reason' => $withdrawal->failure_reason,
                    'proof_path' => $withdrawal->proof_path,
                    'admin_notes' => $withdrawal->admin_notes,
                    'created_at' => $withdrawal->created_at?->toIso8601String(),
                    'processed_at' => $withdrawal->processed_at?->toIso8601String(),
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'mobile' => $user->mobile,
                        'role' => $user->role?->value ?? $user->role,
                    ] : null,
                    'seller' => $profile ? [
                        'business_name' => $profile->displayName(),
                        'store_name' => $profile->store_name,
                        'slug' => $profile->slug,
                        'business_address' => $profile->business_address,
                    ] : null,
                    'wallet' => [
                        'available_balance' => (float) ($wallet?->available_balance ?? 0),
                        'pending_balance' => (float) ($wallet?->pending_balance ?? 0),
                        'total_earnings' => (float) ($wallet?->total_earnings ?? 0),
                        'withdrawn_amount' => (float) ($wallet?->withdrawn_amount ?? 0),
                    ],
                ];
            });

        $counts = [
            'pending_sellers' => Withdrawal::where('status', WithdrawalStatus::Pending)
                ->whereHas('user', fn ($q) => $q->where('role', UserRole::Seller))
                ->count(),
            'pending_buyers' => Withdrawal::where('status', WithdrawalStatus::Pending)
                ->whereHas('user', fn ($q) => $q->where('role', UserRole::Buyer))
                ->count(),
            'processing' => Withdrawal::where('status', WithdrawalStatus::Processing)->count(),
        ];

        return Inertia::render('admin/withdrawals/index', [
            'withdrawals' => $withdrawals,
            'status' => $status,
            'role' => $role,
            'counts' => $counts,
        ]);
    }

    /**
     * Play button — mark withdrawal as processing so the seller sees progress.
     */
    public function start(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        try {
            $this->payouts->startProcessing($withdrawal, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Withdrawal marked as processing. The seller can see this status now.');
    }

    /**
     * Complete payout — optionally attach proof image and notes for the seller.
     */
    public function approve(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        if (! in_array($withdrawal->status, [WithdrawalStatus::Pending, WithdrawalStatus::Processing], true)) {
            return back()->with('error', 'This withdrawal cannot be marked paid.');
        }

        $validated = $request->validate([
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'proof' => ['nullable', 'image', 'max:5120'],
        ]);

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('withdrawal-proofs', 'public');
        }

        $withdrawal->update([
            'processed_by' => $request->user()->id,
            'admin_notes' => $validated['admin_notes'] ?? null,
        ]);

        $this->payouts->markAsPaid(
            $withdrawal->fresh(),
            'manual',
            $proofPath,
            $validated['admin_notes'] ?? null,
        );

        return back()->with('success', 'Payout marked complete. Seller can view'.($proofPath ? ' and download the proof.' : ' the update.'));
    }

    public function reject(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        if (! in_array($withdrawal->status, [WithdrawalStatus::Pending, WithdrawalStatus::Processing], true)) {
            return back()->with('error', 'This withdrawal cannot be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $withdrawal->update([
            'processed_by' => $request->user()->id,
        ]);

        $this->payouts->markAsFailed($withdrawal->fresh(), $validated['rejection_reason']);

        return back()->with('success', 'Withdrawal rejected and funds returned to wallet.');
    }
}
