<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WalletTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->get('status', 'pending');

        $withdrawals = Withdrawal::with('user')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/withdrawals/index', [
            'withdrawals' => $withdrawals,
            'status' => $status,
        ]);
    }

    public function approve(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $withdrawal->update([
            'status' => WithdrawalStatus::Paid,
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        $withdrawal->user->wallet?->increment('withdrawn_amount', $withdrawal->amount);

        WalletTransactionService::recordWithdrawalCompleted($withdrawal);

        return back()->with('success', 'Withdrawal marked as paid.');
    }

    public function reject(Request $request, Withdrawal $withdrawal): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);

        $withdrawal->update([
            'status' => WithdrawalStatus::Rejected,
            'rejection_reason' => $validated['rejection_reason'],
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        $withdrawal->user->wallet?->increment('available_balance', $withdrawal->amount);

        WalletTransactionService::recordWithdrawalRefunded($withdrawal);

        return back()->with('success', 'Withdrawal rejected and funds returned.');
    }
}
