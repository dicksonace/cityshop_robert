<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\PaystackService;
use App\Services\WithdrawalPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function __construct(
        private WithdrawalPayoutService $payouts,
        private PaystackService $paystack,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->get('status', 'pending');
        $role = $request->get('role', 'all');

        $withdrawals = Withdrawal::with('user')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($role === 'seller', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('role', UserRole::Seller)))
            ->when($role === 'buyer', fn ($q) => $q->whereHas('user', fn ($u) => $u->where('role', UserRole::Buyer)))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'pending_sellers' => Withdrawal::where('status', WithdrawalStatus::Pending)
                ->whereHas('user', fn ($q) => $q->where('role', UserRole::Seller))
                ->count(),
            'pending_buyers' => Withdrawal::where('status', WithdrawalStatus::Pending)
                ->whereHas('user', fn ($q) => $q->where('role', UserRole::Buyer))
                ->count(),
        ];

        return Inertia::render('admin/withdrawals/index', [
            'withdrawals' => $withdrawals,
            'status' => $status,
            'role' => $role,
            'counts' => $counts,
            'paystackConfigured' => $this->paystack->isConfigured(),
        ]);
    }

    public function process(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        try {
            $result = $this->payouts->process($withdrawal, $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($result['otp_required']) {
            return back()->with([
                'success' => $result['message'],
                'withdrawal_otp' => [
                    'withdrawal_id' => $withdrawal->id,
                    'transfer_code' => $result['transfer_code'],
                ],
            ]);
        }

        return back()->with('success', $result['message']);
    }

    public function finalize(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'otp' => ['required', 'string', 'max:10'],
        ]);

        try {
            $this->payouts->finalizeWithOtp($withdrawal, $validated['otp'], $request->user());
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'OTP confirmed. Payout will complete shortly.');
    }

    public function approve(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        if (! in_array($withdrawal->status, [WithdrawalStatus::Pending, WithdrawalStatus::Processing], true)) {
            return back()->with('error', 'This withdrawal cannot be marked paid.');
        }

        $withdrawal->update(['processed_by' => $request->user()->id]);

        $this->payouts->markAsPaid($withdrawal->fresh(), 'manual');

        return back()->with('success', 'Manual payout recorded. Funds marked as sent.');
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
