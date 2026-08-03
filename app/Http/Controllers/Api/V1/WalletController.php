<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Enums\WalletTopUpStatus;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Models\WalletTopUpRequest;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\PaystackService;
use App\Services\PlatformSettings;
use App\Services\WalletService;
use App\Services\WalletTransactionService;
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
                'paystack_configured' => $this->paystack->isConfigured(),
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
            ->latest()
            ->paginate($perPage);

        WalletTransactionService::attachRunningBalances(
            $user->id,
            $transactions->getCollection(),
            (float) $wallet->available_balance,
            (float) $wallet->pending_balance,
        );

        return response()->json([
            'data' => $transactions->getCollection()->map(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'type' => $tx->type->value,
                'type_label' => $tx->type->label(),
                'amount' => (float) $tx->amount,
                'description' => $tx->description,
                'reference' => $tx->reference,
                'created_at' => $tx->created_at?->toIso8601String(),
                'balance_before' => $tx->getAttribute('balance_before'),
                'balance_after' => $tx->getAttribute('balance_after'),
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
                'default_account_name' => $user->name,
            ],
        ]);
    }

    /**
     * Mirrors the web buyer withdrawal: the amount leaves the available balance
     * straight away and only comes back if an admin rejects the request.
     */
    public function withdraw(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.self::MINIMUM_WITHDRAWAL],
            'momo_number' => ['required', 'string', 'max:20'],
            'account_name' => ['required', 'string', 'max:255'],
            'network' => ['required', 'in:mtn,telecel,airteltigo'],
        ]);

        $amount = round((float) $validated['amount'], 2);

        return DB::transaction(function () use ($user, $validated, $amount) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first()
                ?? WalletService::ensure($user);

            if ($amount > (float) $wallet->available_balance) {
                return response()->json(['message' => 'Insufficient available balance.'], 422);
            }

            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'momo_number' => $validated['momo_number'],
                'account_name' => $validated['account_name'],
                'network' => $validated['network'],
                'status' => WithdrawalStatus::Pending,
            ]);

            $wallet->decrement('available_balance', $amount);
            WalletTransactionService::recordWithdrawal($withdrawal);

            return response()->json([
                'message' => 'Withdrawal request submitted. Payouts are usually sent within 1 hour.',
                'data' => $this->withdrawalPayload($withdrawal),
                'wallet' => [
                    'available_balance' => (float) $wallet->fresh()->available_balance,
                    'pending_balance' => (float) $wallet->pending_balance,
                ],
            ], 201);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function withdrawalPayload(Withdrawal $withdrawal): array
    {
        return [
            'id' => $withdrawal->id,
            'amount' => (float) $withdrawal->amount,
            'momo_number' => $withdrawal->momo_number,
            'account_name' => $withdrawal->account_name,
            'network' => $withdrawal->network,
            'network_label' => match ($withdrawal->network) {
                'mtn' => 'MTN Mobile Money',
                'telecel' => 'Telecel Cash',
                'airteltigo' => 'AirtelTigo Money',
                default => ucfirst((string) $withdrawal->network),
            },
            'status' => $withdrawal->status?->value,
            // The web deliberately shows pending requests as "Processing".
            'status_label' => match ($withdrawal->status) {
                WithdrawalStatus::Pending, WithdrawalStatus::Processing => 'Processing',
                WithdrawalStatus::Approved => 'Approved',
                WithdrawalStatus::Paid => 'Paid out',
                WithdrawalStatus::Rejected => 'Rejected',
                default => 'Processing',
            },
            'rejection_reason' => $withdrawal->rejection_reason ?? $withdrawal->failure_reason,
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
            'paystack_configured' => $this->paystack->isConfigured(),
            'requests' => WalletTopUpRequest::where('user_id', $request->user()->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (WalletTopUpRequest $item) => [
                    'id' => $item->id,
                    'amount' => (float) $item->amount,
                    'payment_reference' => $item->payment_reference,
                    'status' => $item->status->value,
                    'admin_notes' => $item->admin_notes,
                    'created_at' => $item->created_at?->toIso8601String(),
                    'reviewed_at' => $item->reviewed_at?->toIso8601String(),
                ])
                ->values(),
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

        if (! $this->paystack->isConfigured()) {
            return response()->json(['message' => 'Online top-up is not available. Contact support.'], 503);
        }

        $amount = (float) $validated['amount'];
        $reference = 'TOP-'.strtoupper(uniqid());
        $callbackUrl = url('/api/v1/paystack/mobile-return');

        try {
            $data = $this->paystack->initializeTransaction(
                $user->email,
                $amount,
                $reference,
                [
                    'type' => 'wallet_topup',
                    'user_id' => $user->id,
                    'method' => $validated['method'],
                    'source' => 'mobile_app',
                ],
                $callbackUrl,
            );

            $paystackReference = (string) ($data['reference'] ?? $reference);

            return response()->json([
                'authorization_url' => $data['authorization_url'],
                'access_code' => $data['access_code'] ?? null,
                'reference' => $paystackReference,
                'callback_url' => $callbackUrl,
                'amount' => $amount,
                'currency' => 'GHS',
            ]);
        } catch (\Throwable $e) {
            Log::error('API wallet top-up init failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Could not start payment. Please try again.'], 500);
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

            $amount = round(((int) ($data['amount'] ?? 0)) / 100, 2);
            if ($amount < 5) {
                return response()->json(['message' => 'Invalid payment amount.'], 422);
            }

            $method = (string) ($metadata['method'] ?? 'momo');
            $credited = WalletService::creditFromVerifiedTopUp($user->id, $amount, $reference, $method);
            $wallet = WalletService::ensure($user);

            return response()->json([
                'message' => $credited
                    ? 'GH₵'.number_format($amount, 2).' added to your wallet.'
                    : 'Payment already credited.',
                'amount' => $amount,
                'reference' => $reference,
                'already_credited' => ! $credited,
                'wallet' => [
                    'available_balance' => (float) $wallet->available_balance,
                    'pending_balance' => (float) $wallet->pending_balance,
                    'total_earnings' => (float) $wallet->total_earnings,
                    'withdrawn_amount' => (float) $wallet->withdrawn_amount,
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

        return response()->json([
            'message' => 'Payment proof submitted for verification.',
            'data' => [
                'id' => $topUp->id,
                'amount' => (float) $topUp->amount,
                'payment_reference' => $topUp->payment_reference,
                'network' => $topUp->network,
                'status' => $topUp->status->value,
            ],
        ], 201);
    }
}
