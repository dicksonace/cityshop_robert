<?php

namespace App\Http\Controllers\Shop;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Services\WalletTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;
        $wallet = WalletService::ensure($request->user());

        $transactions = WalletTransaction::where('user_id', $userId)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('shop/wallet', [
            'wallet' => $wallet,
            'transactions' => $transactions,
        ]);
    }

    public function addFunds(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:5', 'max:50000'],
            'method' => ['required', 'in:momo,card'],
        ]);

        $wallet = WalletService::ensure($request->user());
        $amount = (float) $validated['amount'];

        $wallet->increment('available_balance', $amount);
        WalletTransactionService::recordFundAdded($request->user()->id, $amount, $validated['method']);

        return back()->with('success', 'GH₵'.number_format($amount, 2).' added to your wallet.');
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10'],
            'momo_number' => ['required', 'string', 'max:20'],
            'account_name' => ['required', 'string', 'max:255'],
            'network' => ['required', 'in:mtn,telecel,airteltigo'],
        ]);

        $wallet = WalletService::ensure($request->user());

        if ($validated['amount'] > $wallet->available_balance) {
            return back()->with('error', 'Insufficient available balance.');
        }

        $pending = Withdrawal::where('user_id', $request->user()->id)
            ->where('status', WithdrawalStatus::Pending)
            ->exists();

        if ($pending) {
            return back()->with('error', 'You already have a pending withdrawal request.');
        }

        $withdrawal = Withdrawal::create([
            'user_id' => $request->user()->id,
            'amount' => $validated['amount'],
            'momo_number' => $validated['momo_number'],
            'account_name' => $validated['account_name'],
            'network' => $validated['network'],
            'status' => WithdrawalStatus::Pending,
        ]);

        $wallet->decrement('available_balance', $validated['amount']);
        WalletTransactionService::recordWithdrawal($withdrawal);

        return back()->with('success', 'Withdrawal request submitted.');
    }
}
