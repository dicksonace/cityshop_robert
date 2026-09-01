<?php

namespace App\Http\Controllers\Shop;

use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\KycService;
use App\Services\PaymentPinService;
use App\Services\PaystackService;
use App\Services\PlatformSettings;
use App\Services\RmbWalletGuard;
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
    public function __construct(private PaystackService $paystack) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->isBuyer(), 403);

        $userId = $request->user()->id;
        $wallet = WalletService::ensure($request->user());
        $currency = strtoupper(trim((string) $request->query('currency', 'all')));

        $transactions = WalletTransaction::where('user_id', $userId)
            ->where('type', '!=', WalletTransactionType::WithdrawalCompleted)
            ->when(in_array($currency, ['GHS', 'RMB'], true), fn ($q) => $q->where('currency', $currency))
            ->with('withdrawal:id,status,momo_number,fee')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        WalletTransactionService::attachRunningBalances(
            $userId,
            $transactions->getCollection(),
            (float) $wallet->available_balance,
            (float) $wallet->pending_balance,
            (float) $wallet->rmb_balance,
        );
        $transactions->getCollection()->each(function (WalletTransaction $tx) {
            $tx->setAttribute('type_label', WalletTransactionService::displayTypeLabel($tx));
            $tx->setAttribute('description', WalletTransactionService::displayDescription($tx));
            $tx->setAttribute('currency', strtoupper((string) ($tx->currency ?? 'GHS')));
        });

        $withdrawals = Withdrawal::where('user_id', $userId)
            ->latest()
            ->paginate(5, ['*'], 'withdrawals_page')
            ->withQueryString();

        $hasPendingWithdrawal = Withdrawal::where('user_id', $userId)
            ->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Processing])
            ->exists();

        $funding = PlatformSettings::manualFundingAccounts();

        return Inertia::render('shop/wallet', [
            'wallet' => $wallet->toFrontendArray(),
            'transactions' => $transactions,
            'currencyFilter' => in_array($currency, ['GHS', 'RMB'], true) ? $currency : 'all',
            'withdrawals' => $withdrawals,
            'hasPendingWithdrawal' => $hasPendingWithdrawal,
            'paystackConfigured' => $this->paystack->isAvailable(),
            'paystackPublicKey' => config('services.paystack.public_key'),
            'paystackFee' => $this->paystack->rechargeFeePayload(),
            'manualTopUpEnabled' => $funding['enabled'] && count($funding['accounts']) > 0,
            'manualFundingAccounts' => ($funding['enabled'] && count($funding['accounts']) > 0)
                ? $funding['accounts']
                : [],
            'hasPaymentPin' => PaymentPinService::hasPin($request->user()),
            'kyc' => KycService::payload($request->user(), withPhotos: false),
        ]);
    }

    public function createWithdraw(Request $request): Response
    {
        abort_unless($request->user()->isBuyer(), 403);

        $userId = $request->user()->id;
        $wallet = WalletService::ensure($request->user());

        $withdrawals = Withdrawal::where('user_id', $userId)
            ->latest()
            ->paginate(10, ['*'], 'withdrawals_page')
            ->withQueryString();

        $hasPendingWithdrawal = Withdrawal::where('user_id', $userId)
            ->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Processing])
            ->exists();

        return Inertia::render('shop/wallet-withdraw', [
            'wallet' => $wallet->toFrontendArray(),
            'withdrawals' => $withdrawals,
            'hasPendingWithdrawal' => $hasPendingWithdrawal,
            'withdrawalFee' => PlatformSettings::withdrawalFeePayload(),
            'hasPaymentPin' => PaymentPinService::hasPin($request->user()),
        ]);
    }

    public function addFunds(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()->isBuyer(), 403);

        if ($request->expectsJson()) {
            if ($denied = KycService::denyStoreFundsResponse($request->user())) {
                return $denied;
            }
        } elseif (! KycService::canStoreFunds($request->user())) {
            return back()->with('error', KycService::denyStoreFundsMessage($request->user()));
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
                route('wallet.callback'),
                'TOP',
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
            Log::error('Wallet top-up init failed', ['error' => $e->getMessage()]);
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
        abort_unless($request->user()?->isBuyer(), 403);

        $reference = $request->query('reference');

        if (! $reference) {
            return redirect()->route('wallet.index')->with('error', 'Invalid payment reference.');
        }

        try {
            $data = $this->paystack->verifyTransaction($reference);

            if ($data['status'] !== 'success') {
                return redirect()->route('wallet.index')->with('error', 'Payment was not successful.');
            }

            $metadata = $data['metadata'] ?? [];
            if (($metadata['type'] ?? '') !== 'wallet_topup') {
                return redirect()->route('wallet.index')->with('error', 'Invalid wallet top-up.');
            }

            if ((int) ($metadata['user_id'] ?? 0) !== $request->user()->id) {
                return redirect()->route('wallet.index')->with('error', 'Payment does not belong to your account.');
            }

            $paid = round(((int) ($data['amount'] ?? 0)) / 100, 2);
            $method = (string) ($metadata['method'] ?? 'momo');
            $expected = isset($metadata['expected_amount']) ? (float) $metadata['expected_amount'] : null;
            $credit = $this->paystack->topUpCreditFromMetadata($metadata, $paid);

            if ($expected !== null && ! $this->paystack->amountsMatch($paid, $expected)) {
                Log::warning('Wallet top-up amount mismatch', [
                    'reference' => $reference,
                    'paid' => $paid,
                    'expected' => $expected,
                ]);

                return redirect()->route('wallet.index')->with('error', 'Payment amount could not be verified.');
            }

            WalletService::creditFromVerifiedTopUp($request->user()->id, $credit, $reference, $method);

            return redirect()->route('wallet.index')
                ->with('success', 'GH₵'.number_format($credit, 2).' credited to your wallet.');
        } catch (\Throwable $e) {
            Log::error('Wallet callback error', ['error' => $e->getMessage()]);

            return redirect()->route('wallet.index')->with('error', 'Payment verification failed.');
        }
    }

    public function withdraw(Request $request, \App\Services\WithdrawalRequestService $withdrawals): RedirectResponse
    {
        abort_unless($request->user()->isBuyer(), 403);

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
        ], [
            'account_name.not_regex' => 'Enter the name registered on the account, not a number.',
        ]);

        PaymentPinService::assertValidForAction($request->user(), $validated['payment_pin']);

        try {
            $result = $withdrawals->submit($request->user(), [
                'amount' => (float) $validated['amount'],
                'payout_type' => $validated['payout_type'],
                'momo_number' => $validated['momo_number'],
                'account_name' => $validated['account_name'],
                'network' => $validated['network'],
            ]);
        } catch (\InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => [$e->getMessage()],
            ]);
        }

        return redirect()->route('wallet.index')->with('success', $result['message']);
    }

    public function convertForm(Request $request): Response
    {
        abort_unless($request->user()?->canUseRmbWallet(), 403);

        $wallet = WalletService::ensure($request->user());

        $ghsToRmb = null;
        $rmbToGhs = null;
        try {
            $ghsToRmb = WalletService::convertQuote('ghs_to_rmb', 100);
        } catch (\RuntimeException) {
        }
        try {
            $rmbToGhs = WalletService::convertQuote('rmb_to_ghs', 100);
        } catch (\RuntimeException) {
        }

        return Inertia::render('shop/wallet/convert', [
            'wallet' => $wallet->toFrontendArray(),
            'sampleQuotes' => [
                'ghs_to_rmb' => $ghsToRmb,
                'rmb_to_ghs' => $rmbToGhs,
            ],
            'hasPaymentPin' => PaymentPinService::hasPin($request->user()),
            'kyc' => KycService::payload($request->user(), withPhotos: false),
        ]);
    }

    public function convertQuote(Request $request): JsonResponse
    {
        abort_unless($request->user()?->canUseRmbWallet(), 403);

        $validated = $request->validate([
            'direction' => ['required', 'in:ghs_to_rmb,rmb_to_ghs'],
            'amount' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            $quote = WalletService::convertQuote($validated['direction'], (float) $validated['amount']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $wallet = WalletService::ensure($request->user());

        return response()->json([
            'data' => [
                ...$quote,
                'available_balance' => (float) $wallet->available_balance,
                'rmb_balance' => (float) $wallet->rmb_balance,
            ],
        ]);
    }

    public function convert(Request $request): RedirectResponse|JsonResponse
    {
        abort_unless($request->user()?->canUseRmbWallet(), 403);

        if ($denied = RmbWalletGuard::denyRedirect($request->user())) {
            if ($request->expectsJson()) {
                return RmbWalletGuard::denyJson($request->user()) ?? response()->json(['message' => 'KYC required'], 403);
            }

            return $denied;
        }

        $validated = $request->validate([
            'direction' => ['required', 'in:ghs_to_rmb,rmb_to_ghs'],
            'amount' => ['required', 'numeric', 'min:1'],
            'payment_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);

        PaymentPinService::assertValidForAction($request->user(), $validated['payment_pin']);
        RmbWalletGuard::assertConvertVelocity($request->user());

        try {
            $result = WalletService::convert(
                $request->user(),
                $validated['direction'],
                (float) $validated['amount'],
                RmbWalletGuard::requestMeta($request),
            );
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $result['message'],
                'data' => $result,
            ]);
        }

        return redirect()
            ->route('wallet.china-rmb.index')
            ->with('success', $result['message']);
    }
}
