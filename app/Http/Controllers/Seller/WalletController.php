<?php

namespace App\Http\Controllers\Seller;

use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerPayoutMethod;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\PaymentPinService;
use App\Services\PaystackService;
use App\Services\FlutterwaveService;
use App\Services\PlatformSettings;
use App\Services\KycService;
use App\Services\SellerPaymentMethodSecurityService;
use App\Services\WalletService;
use App\Services\WalletTransactionService;
use App\Support\GhanaBanks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(
        private PaystackService $paystack,
        private FlutterwaveService $flutterwave,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        WalletTransactionService::backfillForSeller($user->id);
        $wallet = WalletService::ensure($user);

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->where('type', '!=', WalletTransactionType::WithdrawalCompleted)
            ->with('withdrawal:id,status,momo_number,fee')
            ->latest()
            ->paginate(8, ['*'], 'transactions_page')
            ->withQueryString();

        WalletTransactionService::attachRunningBalances(
            $user->id,
            $transactions->getCollection(),
            (float) $wallet->available_balance,
            (float) $wallet->pending_balance,
        );
        $transactions->getCollection()->each(function (WalletTransaction $tx) {
            $tx->setAttribute('type_label', WalletTransactionService::displayTypeLabel($tx));
            $tx->setAttribute('description', WalletTransactionService::displayDescription($tx));
        });

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
            'wallet' => $wallet->toFrontendArray(),
            'transactions' => $transactions,
            'withdrawals' => $withdrawals,
            'payoutMethods' => $payoutMethods,
            'hasPendingWithdrawal' => Withdrawal::where('user_id', $user->id)
                ->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Processing])
                ->exists(),
            'manualTopUpEnabled' => $funding['enabled'] && count($funding['accounts']) > 0,
            'manualFundingAccounts' => ($funding['enabled'] && count($funding['accounts']) > 0)
                ? $funding['accounts']
                : [],
            'paystackConfigured' => $this->paystack->isAvailable(),
            'paystackFee' => $this->paystack->rechargeFeePayload(),
            'flutterwaveConfigured' => $this->flutterwave->isAvailable(),
            'withdrawalFee' => PlatformSettings::withdrawalFeePayload(),
            'hasPaymentPin' => PaymentPinService::hasPin($user),
            'canUseRmbWallet' => $user->canUseRmbWallet(),
        ]);
    }

    public function transactions(Request $request): Response
    {
        $user = $request->user();
        WalletTransactionService::backfillForSeller($user->id);
        $wallet = $user->wallet;

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->where('type', '!=', WalletTransactionType::WithdrawalCompleted)
            ->with(['orderItem:id,order_id,product_name,status', 'withdrawal:id,status,amount,network,momo_number,fee'])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        WalletTransactionService::attachRunningBalances(
            $user->id,
            $transactions->getCollection(),
            (float) ($wallet?->available_balance ?? 0),
            (float) ($wallet?->pending_balance ?? 0),
        );
        $transactions->getCollection()->each(function (WalletTransaction $tx) {
            $tx->setAttribute('type_label', WalletTransactionService::displayTypeLabel($tx));
            $tx->setAttribute('description', WalletTransactionService::displayDescription($tx));
        });

        return Inertia::render('seller/wallet/transactions', [
            'wallet' => $wallet?->toFrontendArray() ?? Wallet::emptyFrontendArray(),
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

        $transaction->setAttribute('type_label', WalletTransactionService::displayTypeLabel($transaction));
        $transaction->setAttribute('description', WalletTransactionService::displayDescription($transaction));

        return Inertia::render('seller/wallet/transaction-show', [
            'wallet' => $wallet?->toFrontendArray() ?? Wallet::emptyFrontendArray(),
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
            'wallet' => $request->user()->wallet?->toFrontendArray() ?? Wallet::emptyFrontendArray(),
            'withdrawals' => $withdrawals,
        ]);
    }

    public function showWithdrawal(Request $request, Withdrawal $withdrawal): Response
    {
        abort_unless($withdrawal->user_id === $request->user()->id, 403);

        $ledger = WalletTransaction::where('withdrawal_id', $withdrawal->id)
            ->where('type', '!=', WalletTransactionType::WithdrawalCompleted)
            ->orderBy('created_at')
            ->get();

        $ledger->each(function (WalletTransaction $tx) use ($withdrawal) {
            $tx->setRelation('withdrawal', $withdrawal);
            $tx->setAttribute('type_label', WalletTransactionService::displayTypeLabel($tx));
            $tx->setAttribute('description', WalletTransactionService::displayDescription($tx));
        });

        return Inertia::render('seller/wallet/withdrawal-show', [
            'wallet' => $request->user()->wallet?->toFrontendArray() ?? Wallet::emptyFrontendArray(),
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
            'payout_type' => ['required', 'in:momo,bank'],
            'network' => [
                'required',
                'string',
                $request->input('payout_type') === 'bank'
                    ? GhanaBanks::validationRule()
                    : 'in:mtn,telecel,airteltigo',
            ],
            'account_number' => ['required', 'string', 'max:30'],
            'account_name' => ['required', 'string', 'max:255', 'not_regex:/^\d+([.,]\d+)?$/'],
            'is_default' => ['boolean'],
        ], [
            'account_name.not_regex' => 'Enter the name registered on the account, not a number.',
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
            'type' => $validated['payout_type'],
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

    public function withdraw(Request $request, \App\Services\WithdrawalRequestService $withdrawals): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10'],
            'payout_type' => ['required', 'in:momo,bank'],
            'momo_number' => ['required', 'string', 'max:30'],
            'account_name' => ['required', 'string', 'max:255', 'not_regex:/^\d+([.,]\d+)?$/'],
            'network' => [
                'required',
                'string',
                $request->input('payout_type') === 'bank'
                    ? GhanaBanks::validationRule()
                    : 'in:mtn,telecel,airteltigo',
            ],
            'payment_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'payout_method_id' => ['nullable', 'exists:seller_payout_methods,id'],
        ], [
            'account_name.not_regex' => 'Enter the name registered on the account, not a number.',
        ]);

        \App\Services\PaymentPinService::assertValidForAction($request->user(), $validated['payment_pin']);

        $payoutMethodId = null;
        if (! empty($validated['payout_method_id'])) {
            $owned = SellerPayoutMethod::where('user_id', $request->user()->id)
                ->whereKey($validated['payout_method_id'])
                ->exists();
            if ($owned) {
                $payoutMethodId = (int) $validated['payout_method_id'];
            }
        }

        try {
            $result = $withdrawals->submit($request->user(), [
                'amount' => (float) $validated['amount'],
                'payout_type' => $validated['payout_type'],
                'momo_number' => $validated['momo_number'],
                'account_name' => $validated['account_name'],
                'network' => $validated['network'],
                'payout_method_id' => $payoutMethodId,
            ]);
        } catch (\InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => [$e->getMessage()],
            ]);
        }

        return back()->with('success', $result['message']);
    }

    public function addFunds(Request $request): RedirectResponse|JsonResponse
    {
        if ($denied = KycService::denyStoreFundsResponse($request->user())) {
            return $request->expectsJson()
                ? $denied
                : back()->with('error', KycService::denyStoreFundsMessage($request->user()));
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:5', 'max:50000'],
            'method' => ['required', 'in:momo,card'],
        ]);

        if (! $this->paystack->isAvailable()) {
            $message = $this->paystack->unavailableMessage();

            return $request->expectsJson()
                ? response()->json(['message' => $message], 503)
                : back()->with('error', $message);
        }

        try {
            $data = $this->paystack->initializeWalletTopUp(
                $request->user(),
                (float) $validated['amount'],
                $validated['method'],
                route('seller.wallet.callback'),
                'TOP',
                ['role' => 'seller'],
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'authorization_url' => $data['authorization_url'],
                    'access_code' => $data['access_code'],
                    'reference' => $data['reference'],
                    'email' => $data['email'],
                    'amount' => $data['credit'],
                    'fee' => $data['fee'],
                    'charge' => $data['charge'],
                ]);
            }

            return Inertia::location($data['authorization_url']);
        } catch (\Throwable $e) {
            Log::error('Seller wallet top-up init failed', ['error' => $e->getMessage()]);
            $message = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Could not start payment. Please try again.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 500)
                : back()->with('error', $message);
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $reference = $request->query('reference');

        if (! $reference) {
            return redirect()->route('seller.wallet')->with('error', 'Invalid payment reference.');
        }

        try {
            $data = $this->paystack->verifyTransaction($reference);

            if ($data['status'] !== 'success') {
                return redirect()->route('seller.wallet')->with('error', 'Payment was not successful.');
            }

            $metadata = $data['metadata'] ?? [];
            if (($metadata['type'] ?? '') !== 'wallet_topup') {
                return redirect()->route('seller.wallet')->with('error', 'Invalid wallet top-up.');
            }

            if ((int) ($metadata['user_id'] ?? 0) !== $request->user()->id) {
                return redirect()->route('seller.wallet')->with('error', 'Payment does not belong to your account.');
            }

            $paid = round(((int) ($data['amount'] ?? 0)) / 100, 2);
            $method = (string) ($metadata['method'] ?? 'momo');
            $expected = isset($metadata['expected_amount']) ? (float) $metadata['expected_amount'] : null;
            $credit = $this->paystack->topUpCreditFromMetadata($metadata, $paid);

            if ($expected !== null && ! $this->paystack->amountsMatch($paid, $expected)) {
                Log::warning('Seller wallet top-up amount mismatch', [
                    'reference' => $reference,
                    'paid' => $paid,
                    'expected' => $expected,
                ]);

                return redirect()->route('seller.wallet')->with('error', 'Payment amount could not be verified.');
            }

            WalletService::creditFromVerifiedTopUp($request->user()->id, $credit, $reference, $method);

            return redirect()->route('seller.dashboard')
                ->with('success', 'GH₵'.number_format($credit, 2).' credited to your wallet. You can cancel Pay-to-seller orders and refund buyers.');
        } catch (\Throwable $e) {
            Log::error('Seller wallet callback error', ['error' => $e->getMessage()]);

            return redirect()->route('seller.wallet')->with('error', 'Could not verify payment. Contact support if charged.');
        }
    }

    public function addFundsFlutterwave(Request $request): RedirectResponse|JsonResponse
    {
        if ($denied = KycService::denyStoreFundsResponse($request->user())) {
            return $request->expectsJson()
                ? $denied
                : back()->with('error', KycService::denyStoreFundsMessage($request->user()));
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:5', 'max:50000'],
            'method' => ['required', 'in:momo,card'],
        ]);

        if (! $this->flutterwave->isAvailable()) {
            $message = $this->flutterwave->unavailableMessage();

            return $request->expectsJson()
                ? response()->json(['message' => $message], 503)
                : back()->with('error', $message);
        }

        try {
            $data = $this->flutterwave->initializeWalletTopUp(
                $request->user(),
                (float) $validated['amount'],
                $validated['method'],
                route('seller.wallet.flutterwave.callback'),
                'FLW-TOP',
                ['role' => 'seller'],
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'authorization_url' => $data['authorization_url'],
                    'reference' => $data['reference'],
                    'email' => $data['email'],
                    'amount' => $data['credit'],
                    'fee' => $data['fee'],
                    'charge' => $data['charge'],
                    'gateway' => 'flutterwave',
                ]);
            }

            return Inertia::location($data['authorization_url']);
        } catch (\Throwable $e) {
            Log::error('Seller Flutterwave wallet top-up init failed', ['error' => $e->getMessage()]);
            $message = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Could not start payment. Please try again.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 500)
                : back()->with('error', $message);
        }
    }

    public function flutterwaveCallback(Request $request): RedirectResponse
    {
        $reference = (string) ($request->query('tx_ref') ?: $request->query('reference') ?: '');

        if ($reference === '') {
            return redirect()->route('seller.wallet')->with('error', 'Invalid payment reference.');
        }

        try {
            $data = $this->flutterwave->verifyByReference($reference);

            if (! $this->flutterwave->isSuccessful($data)) {
                return redirect()->route('seller.wallet')->with('error', 'Payment was not successful.');
            }

            $metadata = $this->flutterwave->normalizeMeta($data);
            if (($metadata['type'] ?? '') !== 'wallet_topup') {
                return redirect()->route('seller.wallet')->with('error', 'Invalid wallet top-up.');
            }

            if ((int) ($metadata['user_id'] ?? 0) !== $request->user()->id) {
                return redirect()->route('seller.wallet')->with('error', 'Payment does not belong to your account.');
            }

            $paid = $this->flutterwave->paidAmountGhs($data);
            $method = (string) ($metadata['method'] ?? 'momo');
            $expected = isset($metadata['expected_amount']) ? (float) $metadata['expected_amount'] : null;
            $credit = $this->flutterwave->topUpCreditFromMetadata($metadata, $paid);

            if ($expected !== null && ! $this->flutterwave->amountsMatch($paid, $expected)) {
                Log::warning('Seller Flutterwave wallet top-up amount mismatch', [
                    'reference' => $reference,
                    'paid' => $paid,
                    'expected' => $expected,
                ]);

                return redirect()->route('seller.wallet')->with('error', 'Payment amount could not be verified.');
            }

            WalletService::creditFromVerifiedTopUp($request->user()->id, $credit, $reference, $method);

            return redirect()->route('seller.dashboard')
                ->with('success', 'GH₵'.number_format($credit, 2).' credited to your wallet. You can cancel Pay-to-seller orders and refund buyers.');
        } catch (\Throwable $e) {
            Log::error('Seller Flutterwave wallet callback error', ['error' => $e->getMessage()]);

            return redirect()->route('seller.wallet')->with('error', 'Could not verify payment. Contact support if charged.');
        }
    }
}
