<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Enums\WalletTopUpStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTopUpRequest;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\AdminNotifier;
use App\Services\PaystackService;
use App\Services\PaymentPinService;
use App\Services\PlatformSettings;
use App\Services\WalletService;
use App\Services\WalletTransactionService;
use App\Support\GhanaBanks;
use App\Support\PayoutNetwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    /** Same floor the web wallet enforces. */
    private const MINIMUM_WITHDRAWAL = 10;

    public function __construct(private PaystackService $paystack) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $wallet = WalletService::ensure($user);
        $funding = PlatformSettings::manualFundingAccounts();

        return response()->json([
            'data' => [
                'available_balance' => (float) $wallet->available_balance,
                'pending_balance' => (float) $wallet->pending_balance,
                'total_earnings' => (float) $wallet->total_earnings,
                'withdrawn_amount' => (float) $wallet->withdrawn_amount,
                'paystack_configured' => $this->paystack->isAvailable(),
                'paystack_fee' => $this->paystack->rechargeFeePayload(),
                'manual_top_up_enabled' => $funding['enabled'] && count($funding['accounts']) > 0,
            ],
        ]);
    }

    /**
     * Wallet ledger, newest first, with the running balance around each entry —
     * the same view the web wallet page shows.
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $wallet = WalletService::ensure($user);
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 50);

        $transactions = WalletTransaction::where('user_id', $user->id)
            ->where('type', '!=', WalletTransactionType::WithdrawalCompleted)
            ->with('withdrawal:id,status,momo_number,fee')
            ->latest()
            ->paginate($perPage);

        WalletTransactionService::attachRunningBalances(
            $user->id,
            $transactions->getCollection(),
            (float) $wallet->available_balance,
            (float) $wallet->pending_balance,
        );
        WalletTransactionService::attachCounterpartyMobiles($transactions->getCollection());

        return response()->json([
            'data' => $transactions->getCollection()->map(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type->value,
                'type_label' => WalletTransactionService::displayTypeLabel($tx),
                'amount' => (float) $tx->amount,
                'description' => WalletTransactionService::displayDescription($tx),
                'reference' => $tx->reference,
                'created_at' => $tx->created_at?->toIso8601String(),
                'balance_before' => $tx->getAttribute('balance_before'),
                'balance_after' => $tx->getAttribute('balance_after'),
                'counterparty' => WalletTransactionService::counterpartyPayload($tx),
            ])->values(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    /**
     * Look up one ledger row by transfer/payment reference (chat receipts).
     */
    public function transactionByReference(Request $request, string $reference): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $reference = trim($reference);
        abort_if($reference === '', 404);

        $wallet = WalletService::ensure($user);
        $tx = WalletTransaction::where('user_id', $user->id)
            ->where('reference', $reference)
            ->where('type', '!=', WalletTransactionType::WithdrawalCompleted)
            ->with('withdrawal:id,status,momo_number,fee')
            ->latest('id')
            ->first();

        if (! $tx) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        WalletTransactionService::attachRunningBalances(
            $user->id,
            collect([$tx]),
            (float) $wallet->available_balance,
            (float) $wallet->pending_balance,
        );
        WalletTransactionService::attachCounterpartyMobiles(collect([$tx]));

        return response()->json([
            'transaction' => [
                'id' => $tx->id,
                'type' => $tx->type->value,
                'type_label' => WalletTransactionService::displayTypeLabel($tx),
                'amount' => (float) $tx->amount,
                'description' => WalletTransactionService::displayDescription($tx),
                'reference' => $tx->reference,
                'created_at' => $tx->created_at?->toIso8601String(),
                'balance_before' => $tx->getAttribute('balance_before'),
                'balance_after' => $tx->getAttribute('balance_after'),
                'counterparty' => WalletTransactionService::counterpartyPayload($tx),
            ],
        ]);
    }

    /**
     * Everything the app needs to draw the withdraw screen: how much can leave
     * the wallet, whether a request is already in flight, and past payouts.
     */
    public function withdrawals(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $wallet = WalletService::ensure($user);
        $withdrawals = Withdrawal::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $withdrawals->map(fn (Withdrawal $item) => $this->withdrawalPayload($item))->values(),
            'summary' => [
                'available_balance' => (float) $wallet->available_balance,
                'pending_balance' => (float) $wallet->pending_balance,
                'withdrawn_amount' => (float) $wallet->withdrawn_amount,
                'minimum' => self::MINIMUM_WITHDRAWAL,
                'has_pending' => $withdrawals->contains(
                    fn (Withdrawal $item) => in_array(
                        $item->status,
                        [WithdrawalStatus::Pending, WithdrawalStatus::Processing],
                        true,
                    ),
                ),
                'default_momo_number' => $user->mobile,
                'default_account_name' => null,
                'banks' => collect(GhanaBanks::OPTIONS)
                    ->map(fn (string $label, string $id) => ['id' => $id, 'label' => $label])
                    ->values()
                    ->all(),
                'withdrawal_fee' => PlatformSettings::withdrawalFeePayload(),
            ],
        ]);
    }

    /**
     * Mirrors the web buyer withdrawal: the amount leaves the available balance
     * straight away and only comes back if an admin rejects the request.
     */
    public function withdraw(Request $request, \App\Services\WithdrawalRequestService $withdrawals): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $payoutType = $request->input('payout_type');
        // Infer bank when the client sends a bank code without payout_type.
        if (! in_array($payoutType, ['momo', 'bank'], true)) {
            $payoutType = GhanaBanks::isBank($request->input('network')) ? 'bank' : 'momo';
            $request->merge(['payout_type' => $payoutType]);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.self::MINIMUM_WITHDRAWAL],
            'payout_type' => ['required', 'in:momo,bank'],
            'momo_number' => ['required', 'string', 'max:30'],
            'account_name' => ['required', 'string', 'max:255', 'not_regex:/^\d+([.,]\d+)?$/'],
            'network' => [
                'required',
                'string',
                $payoutType === 'bank'
                    ? GhanaBanks::validationRule()
                    : 'in:mtn,telecel,airteltigo',
            ],
            'payment_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ], [
            'account_name.not_regex' => 'Enter the name registered on the account, not a number.',
        ]);

        PaymentPinService::assertValidForAction($user, $validated['payment_pin']);

        try {
            $result = $withdrawals->submit($user, [
                'amount' => (float) $validated['amount'],
                'payout_type' => $validated['payout_type'],
                'momo_number' => $validated['momo_number'],
                'account_name' => $validated['account_name'],
                'network' => $validated['network'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => $result['message'],
            'data' => $this->withdrawalPayload($result['withdrawal']),
            'wallet' => [
                'available_balance' => (float) $result['wallet']->available_balance,
                'pending_balance' => (float) $result['wallet']->pending_balance,
            ],
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function withdrawalPayload(Withdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'amount' => (float) $withdrawal->amount,
            'fee' => (float) ($withdrawal->fee ?? 0),
            'total_debited' => $withdrawal->totalDebited(),
            'momo_number' => $withdrawal->momo_number,
            'account_name' => $withdrawal->account_name,
            'network' => $withdrawal->network,
            'network_label' => PayoutNetwork::label($withdrawal->network),
            'payout_type' => in_array($withdrawal->payout_channel, ['momo', 'bank'], true)
                ? $withdrawal->payout_channel
                : PayoutNetwork::type($withdrawal->network),
            'payout_channel' => $withdrawal->payout_channel,
            'payout_channel_label' => match ($withdrawal->payout_channel) {
                'paystack' => 'Paystack',
                'bank' => 'Bank transfer',
                'momo' => 'Mobile Money',
                'manual' => 'Manual payout',
                default => $withdrawal->payout_channel
                    ? ucfirst((string) $withdrawal->payout_channel)
                    : 'Manual payout',
            },
            'reference' => 'WD-'.$withdrawal->id,
            'status' => $withdrawal->status?->value,
            // The web deliberately shows pending requests as "Processing".
            'status_label' => match ($withdrawal->status) {
                WithdrawalStatus::Pending, WithdrawalStatus::Processing => 'Processing',
                WithdrawalStatus::Approved => 'Approved',
                WithdrawalStatus::Paid => 'Completed',
                WithdrawalStatus::Rejected => 'Rejected',
                default => 'Processing',
            },
            'rejection_reason' => $withdrawal->rejection_reason,
            'failure_reason' => $withdrawal->failure_reason,
            'admin_notes' => $withdrawal->admin_notes,
            'proof_url' => $withdrawal->proof_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($withdrawal->proof_path)
                : null,
            'created_at' => $withdrawal->created_at?->toIso8601String(),
            'processed_at' => $withdrawal->processed_at?->toIso8601String(),
        ];
    }

    public function manualFunding(Request $request): JsonResponse
    {
        $settings = PlatformSettings::manualFundingAccounts();

        return response()->json([
            'enabled' => $settings['enabled'],
            'instructions' => $settings['instructions'],
            'accounts' => $settings['accounts'],
            'paystack_configured' => $this->paystack->isAvailable(),
            'requests' => WalletTopUpRequest::where('user_id', $request->user()->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (WalletTopUpRequest $item) => $this->manualTopUpPayload($item))
                ->values(),
        ]);
    }

    public function showManualTopUp(Request $request, WalletTopUpRequest $topUp): JsonResponse
    {
        abort_unless((int) $topUp->user_id === (int) $request->user()->id, 403);

        return response()->json(['data' => $this->manualTopUpPayload($topUp)]);
    }

    public function cancelManualTopUp(Request $request, WalletTopUpRequest $topUp): JsonResponse
    {
        abort_unless((int) $topUp->user_id === (int) $request->user()->id, 403);

        if ($topUp->status !== WalletTopUpStatus::Pending) {
            return response()->json(['message' => 'Only pending deposits can be cancelled.'], 422);
        }

        $topUp->update([
            'status' => WalletTopUpStatus::Cancelled,
            'admin_notes' => 'Cancelled by user.',
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Deposit request cancelled.',
            'data' => $this->manualTopUpPayload($topUp->fresh()),
        ]);
    }

    public function initializePaystackTopUp(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:5', 'max:50000'],
            'method' => ['required', 'in:momo,card'],
        ]);

        if (! $this->paystack->isAvailable()) {
            return response()->json(['message' => $this->paystack->unavailableMessage()], 503);
        }

        $callbackUrl = url('/api/v1/paystack/mobile-return');

        try {
            $data = $this->paystack->initializeWalletTopUp(
                $user,
                (float) $validated['amount'],
                $validated['method'],
                $callbackUrl,
                'TOP',
                ['source' => 'mobile_app'],
            );

            return response()->json([
                'authorization_url' => $data['authorization_url'],
                'access_code' => $data['access_code'],
                'reference' => $data['reference'],
                'callback_url' => $callbackUrl,
                'amount' => $data['credit'],
                'fee' => $data['fee'],
                'charge' => $data['charge'],
                'currency' => 'GHS',
            ]);
        } catch (\Throwable $e) {
            Log::error('API wallet top-up init failed', ['error' => $e->getMessage()]);
            $message = $e instanceof \RuntimeException
                ? $e->getMessage()
                : 'Could not start payment. Please try again.';

            return response()->json(['message' => $message], 500);
        }
    }

    public function verifyPaystackTopUp(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
        ]);

        $reference = $validated['reference'];

        try {
            $data = $this->paystack->verifyTransaction($reference);

            if (($data['status'] ?? '') !== 'success') {
                return response()->json(['message' => 'Payment was not successful.'], 422);
            }

            $metadata = $data['metadata'] ?? [];
            if (($metadata['type'] ?? '') !== 'wallet_topup') {
                return response()->json(['message' => 'Invalid wallet top-up.'], 422);
            }

            if ((int) ($metadata['user_id'] ?? 0) !== $user->id) {
                return response()->json(['message' => 'Payment does not belong to your account.'], 403);
            }

            $paid = round(((int) ($data['amount'] ?? 0)) / 100, 2);
            $credit = $this->paystack->topUpCreditFromMetadata($metadata, $paid);
            if ($credit < 5) {
                return response()->json(['message' => 'Invalid payment amount.'], 422);
            }

            $expected = isset($metadata['expected_amount']) ? (float) $metadata['expected_amount'] : null;
            if ($expected !== null && ! $this->paystack->amountsMatch($paid, $expected)) {
                return response()->json(['message' => 'Payment amount could not be verified.'], 422);
            }

            $method = (string) ($metadata['method'] ?? 'momo');
            $credited = WalletService::creditFromVerifiedTopUp($user->id, $credit, $reference, $method);
            $wallet = WalletService::ensure($user);

            return response()->json([
                'message' => $credited
                    ? 'GH₵'.number_format($credit, 2).' credited to your wallet.'
                    : 'Payment already credited.',
                'amount' => $credit,
                'reference' => $reference,
                'already_credited' => ! $credited,
                'wallet' => [
                    'available_balance' => (float) $wallet->available_balance,
                    'pending_balance' => (float) $wallet->pending_balance,
                    'total_earnings' => (float) $wallet->total_earnings,
                    'withdrawn_amount' => (float) $wallet->withdrawn_amount,
                    'paystack_configured' => $this->paystack->isAvailable(),
                    'paystack_fee' => $this->paystack->rechargeFeePayload(),
                    'manual_top_up_enabled' => PlatformSettings::manualFundingAccounts()['enabled'] ?? false,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('API wallet top-up verify failed', ['error' => $e->getMessage(), 'reference' => $reference]);

            return response()->json(['message' => 'Payment verification failed.'], 422);
        }
    }

    public function manualTopUp(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $settings = PlatformSettings::manualFundingAccounts();

        if (! $settings['enabled'] || count($settings['accounts']) === 0) {
            return response()->json(['message' => 'Manual top-up is not available right now.'], 422);
        }

        if (WalletTopUpRequest::where('user_id', $user->id)->where('status', WalletTopUpStatus::Pending)->exists()) {
            return response()->json(['message' => 'You already have a pending manual top-up.'], 422);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10', 'max:500000'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'network' => ['required', 'string', 'in:mtn,telecel,airteltigo'],
            'user_note' => ['nullable', 'string', 'max:500'],
            'proof' => ['required', 'image', 'max:5120'],
        ]);

        $proofPath = $request->file('proof')->store('wallet-top-up-proofs', 'public');

        $topUp = WalletTopUpRequest::create([
            'user_id' => $user->id,
            'amount' => $validated['amount'],
            'payment_reference' => trim((string) ($validated['payment_reference'] ?? '')),
            'sender_name' => null,
            'sender_number' => null,
            'network' => $validated['network'],
            'proof_path' => $proofPath,
            'user_note' => $validated['user_note'] ?? null,
            'status' => WalletTopUpStatus::Pending,
        ]);

        try {
            AdminNotifier::depositProof($user, $topUp);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'Payment proof submitted for verification.',
            'data' => $this->manualTopUpPayload($topUp),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function manualTopUpPayload(WalletTopUpRequest $item): array
    {
        return [
            'id' => $item->id,
            'amount' => (float) $item->amount,
            'payment_reference' => $item->payment_reference,
            'network' => $item->network,
            'user_note' => $item->user_note,
            'status' => $item->status->value,
            'admin_notes' => $item->admin_notes,
            'proof_url' => $item->proof_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($item->proof_path) : null,
            'created_at' => $item->created_at?->toIso8601String(),
            'reviewed_at' => $item->reviewed_at?->toIso8601String(),
        ];
    }
}
