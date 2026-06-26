<?php

namespace App\Http\Controllers\Seller;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerPayoutMethod;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\WalletTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        WalletTransactionService::backfillForSeller($user->id);

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->latest()
            ->paginate(15, ['*'], 'transactions_page')
            ->withQueryString();

        $withdrawals = Withdrawal::where('user_id', $user->id)
            ->latest()
            ->paginate(10, ['*'], 'withdrawals_page')
            ->withQueryString();

        $payoutMethods = SellerPayoutMethod::where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        return Inertia::render('seller/wallet', [
            'wallet' => $user->wallet,
            'transactions' => $transactions,
            'withdrawals' => $withdrawals,
            'payoutMethods' => $payoutMethods,
            'hasPendingWithdrawal' => Withdrawal::where('user_id', $user->id)
                ->where('status', WithdrawalStatus::Pending)
                ->exists(),
        ]);
    }

    public function storePayoutMethod(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'network' => ['required', 'in:mtn,telecel,airteltigo'],
            'account_number' => ['required', 'string', 'max:20'],
            'account_name' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ]);

        if ($validated['is_default'] ?? false) {
            SellerPayoutMethod::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        $isFirst = ! SellerPayoutMethod::where('user_id', $request->user()->id)->exists();

        SellerPayoutMethod::create([
            'user_id' => $request->user()->id,
            'type' => 'momo',
            'network' => $validated['network'],
            'account_number' => $validated['account_number'],
            'account_name' => $validated['account_name'],
            'is_default' => ($validated['is_default'] ?? false) || $isFirst,
        ]);

        return back()->with('success', 'Payout method saved.');
    }

    public function destroyPayoutMethod(Request $request, SellerPayoutMethod $payoutMethod): RedirectResponse
    {
        abort_unless($payoutMethod->user_id === $request->user()->id, 403);

        $wasDefault = $payoutMethod->is_default;
        $payoutMethod->delete();

        if ($wasDefault) {
            SellerPayoutMethod::where('user_id', $request->user()->id)
                ->latest()
                ->first()
                ?->update(['is_default' => true]);
        }

        return back()->with('success', 'Payout method removed.');
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10'],
            'payout_method_id' => ['required', 'exists:seller_payout_methods,id'],
        ]);

        $payoutMethod = SellerPayoutMethod::where('user_id', $request->user()->id)
            ->findOrFail($validated['payout_method_id']);

        $wallet = $request->user()->wallet;

        if (! $wallet || $validated['amount'] > $wallet->available_balance) {
            return back()->with('error', 'Insufficient available balance.');
        }

        if (Withdrawal::where('user_id', $request->user()->id)->where('status', WithdrawalStatus::Pending)->exists()) {
            return back()->with('error', 'You already have a pending withdrawal request.');
        }

        $withdrawal = Withdrawal::create([
            'user_id' => $request->user()->id,
            'payout_method_id' => $payoutMethod->id,
            'amount' => $validated['amount'],
            'momo_number' => $payoutMethod->account_number,
            'account_name' => $payoutMethod->account_name,
            'network' => $payoutMethod->network,
            'status' => WithdrawalStatus::Pending,
        ]);

        $wallet->decrement('available_balance', $validated['amount']);
        WalletTransactionService::recordWithdrawal($withdrawal);

        return back()->with('success', 'Withdrawal request submitted. Processing typically takes 1–3 business days.');
    }
}
