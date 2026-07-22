<?php

namespace App\Http\Controllers\Seller;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerPayoutMethod;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\PlatformSettings;
use App\Services\SellerPaymentMethodSecurityService;
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
        $wallet = $user->wallet;

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->latest()
            ->paginate(8, ['*'], 'transactions_page')
            ->withQueryString();

        WalletTransactionService::attachRunningBalances(
            $user->id,
            $transactions->getCollection(),
            (float) ($wallet?->available_balance ?? 0),
            (float) ($wallet?->pending_balance ?? 0),
        );

        $withdrawals = Withdrawal::where('user_id', $user->id)
            ->latest()
            ->paginate(5, ['*'], 'withdrawals_page')
            ->withQueryString();

        $payoutMethods = SellerPayoutMethod::where('user_id', $user->id)
            ->orderByRaw("CASE network WHEN 'mtn' THEN 0 WHEN 'telecel' THEN 1 WHEN 'airteltigo' THEN 2 ELSE 3 END")
            ->orderByDesc('is_default')
            ->latest()
            ->get();

        $funding = PlatformSettings::manualFundingAccounts();

        return Inertia::render('seller/wallet', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'withdrawals' => $withdrawals,
            'payoutMethods' => $payoutMethods,
            'hasPendingWithdrawal' => Withdrawal::where('user_id', $user->id)
                ->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Processing])
                ->exists(),
            'manualTopUpEnabled' => $funding['enabled'] && count($funding['accounts']) > 0,
        ]);
    }

    public function transactions(Request $request): Response
    {
        $user = $request->user();
        WalletTransactionService::backfillForSeller($user->id);
        $wallet = $user->wallet;

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->with(['orderItem:id,order_id,product_name,status', 'withdrawal:id,status,amount,network,momo_number'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        WalletTransactionService::attachRunningBalances(
            $user->id,
            $transactions->getCollection(),
            (float) ($wallet?->available_balance ?? 0),
            (float) ($wallet?->pending_balance ?? 0),
        );

        return Inertia::render('seller/wallet/transactions', [
            'wallet' => $wallet,
            'transactions' => $transactions,
        ]);
    }

    public function showTransaction(Request $request, WalletTransaction $transaction): Response
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);

        $wallet = $request->user()->wallet;
        $balances = WalletTransactionService::balancesAfterTransaction(
            $transaction,
            (float) ($wallet?->available_balance ?? 0),
            (float) ($wallet?->pending_balance ?? 0),
        );

        $transaction->load([
            'orderItem:id,order_id,product_name,status,seller_amount,quantity',
            'orderItem.order:id,order_number,status,payment_status,created_at',
            'withdrawal',
        ]);

        return Inertia::render('seller/wallet/transaction-show', [
            'wallet' => $wallet,
            'transaction' => array_merge($transaction->toArray(), $balances),
        ]);
    }

    public function withdrawals(Request $request): Response
    {
        $withdrawals = Withdrawal::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('seller/wallet/withdrawals', [
            'wallet' => $request->user()->wallet,
            'withdrawals' => $withdrawals,
        ]);
    }

    public function showWithdrawal(Request $request, Withdrawal $withdrawal): Response
    {
        abort_unless($withdrawal->user_id === $request->user()->id, 403);

        $ledger = WalletTransaction::where('withdrawal_id', $withdrawal->id)
            ->orderBy('created_at')
            ->get();

        return Inertia::render('seller/wallet/withdrawal-show', [
            'wallet' => $request->user()->wallet,
            'withdrawal' => $withdrawal,
            'ledger' => $ledger,
        ]);
    }

    public function storePayoutMethod(Request $request, SellerPaymentMethodSecurityService $security): RedirectResponse
    {
        $profile = $request->user()->sellerProfile;
        if ($profile) {
            try {
                $security->assertCanManagePaymentMethods($profile);
            } catch (\InvalidArgumentException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

        $validated = $request->validate([
            'network' => ['required', 'in:mtn,telecel,airteltigo'],
            'account_number' => ['required', 'string', 'max:20'],
            'account_name' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
        ]);

        if ($profile) {
            try {
                $security->assertAccountNotBlocked($profile, $validated['account_number']);
            } catch (\InvalidArgumentException $e) {
                return back()->with('error', $e->getMessage());
            }
        }

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

        if (Withdrawal::where('user_id', $request->user()->id)
            ->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Processing])
            ->exists()) {
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

        return back()->with('success', 'Withdrawal request submitted. Processing typically takes 1 hour.');
    }
}
