<?php

namespace App\Services;

use App\Enums\ChinaTransferStatus;
use App\Enums\WalletTransactionType;
use App\Models\ChinaTransfer;
use App\Models\ChinaTransferAdminNote;
use App\Models\ChinaTransferFieldValue;
use App\Models\ChinaTransferFormField;
use App\Models\ChinaTransferPaymentMethod;
use App\Models\ChinaTransferProof;
use App\Models\ChinaTransferRate;
use App\Models\ChinaTransferSetting;
use App\Models\ChinaTransferStatusHistory;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\ChinaTransferAdminNotification;
use App\Notifications\ChinaTransferUserNotification;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChinaTransferService
{
    public function settings(): ChinaTransferSetting
    {
        return ChinaTransferSetting::current();
    }

    public function currentRate(): ?ChinaTransferRate
    {
        $now = now();

        return ChinaTransferRate::query()
            ->where('active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>', $now);
            })
            ->latest('id')
            ->first();
    }

    public function isOpen(): bool
    {
        $settings = $this->settings();

        return $settings->enabled
            && $settings->channel === 'alipay'
            && $this->currentRate() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function transferHoursPayload(): array
    {
        $settings = $this->settings();
        $timezone = config('app.timezone', 'Africa/Accra');
        $openRaw = $settings->transfer_open_time;
        $closeRaw = $settings->transfer_close_time;

        if (! $openRaw || ! $closeRaw) {
            return [
                'configured' => false,
                'timezone' => $timezone,
                'is_open_now' => true,
                'in_processing_window' => true,
                'open_time' => null,
                'close_time' => null,
                'open_time_label' => null,
                'close_time_label' => null,
                'processing_note' => null,
                'closed_message' => null,
            ];
        }

        $openTime = $this->normalizeTimeString((string) $openRaw);
        $closeTime = $this->normalizeTimeString((string) $closeRaw);
        $now = now($timezone);
        $openToday = $now->copy()->setTimeFromTimeString($openTime);
        $closeToday = $now->copy()->setTimeFromTimeString($closeTime);
        $isOpenNow = $now->greaterThanOrEqualTo($openToday) && $now->lessThan($closeToday);
        $openLabel = $openToday->format('g:i A');

        return [
            'configured' => true,
            'timezone' => $timezone,
            // Never auto-close buyers — admin Live/Pause toggle is the only gate.
            'is_open_now' => true,
            'in_processing_window' => $isOpenNow,
            'open_time' => substr($openTime, 0, 5),
            'close_time' => substr($closeTime, 0, 5),
            'open_time_label' => $openLabel,
            'close_time_label' => $closeToday->format('g:i A'),
            'processing_note' => $isOpenNow
                ? null
                : "Transfers submitted now will be processed by {$openLabel}.",
            'closed_message' => null,
        ];
    }

    private function normalizeTimeString(string $time): string
    {
        $parts = explode(':', $time);

        return sprintf('%02d:%02d:00', (int) ($parts[0] ?? 0), (int) ($parts[1] ?? 0));
    }

    public function hasExternalPaymentMethods(): bool
    {
        return ChinaTransferPaymentMethod::query()->where('active', true)->exists();
    }

    public function isExternalOpen(): bool
    {
        return $this->isOpen() && $this->hasExternalPaymentMethods();
    }

    /**
     * @return array<string, mixed>
     */
    public function quote(float $ghsAmount, ?ChinaTransferRate $rate = null): array
    {
        $rate ??= $this->currentRate();
        if (! $rate) {
            throw ValidationException::withMessages([
                'ghs_amount' => 'China Transfer is not open. Admin has not published a rate yet.',
            ]);
        }

        $ghs = round($ghsAmount, 2);
        $ghsPerRmb = $rate->effectiveGhsPerRmb();
        if ($ghsPerRmb <= 0) {
            throw ValidationException::withMessages(['ghs_amount' => 'The published rate is invalid.']);
        }

        $rmbPerGhs = $rate->rmbPerGhs();
        $rmb = round($ghs * $rmbPerGhs, 2);
        $fee = $rate->fee_mode === 'percent'
            ? round($ghs * ((float) $rate->fee_value) / 100, 2)
            : round((float) $rate->fee_value, 2);
        $total = round($ghs + $fee, 2);

        return [
            'ghs_amount' => $ghs,
            'ghs_per_rmb' => $ghsPerRmb,
            'rmb_per_ghs' => $rmbPerGhs,
            'rmb_amount' => $rmb,
            'fee_mode' => $rate->fee_mode,
            'fee_value' => (float) $rate->fee_value,
            'fee_ghs' => $fee,
            'total_payable_ghs' => $total,
            'rate_id' => $rate->id,
            'min_ghs' => (float) $rate->min_ghs,
            'max_ghs' => (float) $rate->max_ghs,
            'breakdown' => [
                'send' => 'GH₵'.number_format($ghs, 2),
                'rate' => '1 RMB = GH₵'.number_format($ghsPerRmb, 4),
                'rate_ghs' => '1 GHS → ¥'.number_format($rate->rmbPerGhs(), 3),
                'rmb' => '¥'.number_format($rmb, 2),
                'fee' => 'GH₵'.number_format($fee, 2),
                'total' => 'GH₵'.number_format($total, 2),
            ],
        ];
    }

    public function create(User $user, Request $request): ChinaTransfer
    {
        if (! $this->isOpen()) {
            throw ValidationException::withMessages([
                'ghs_amount' => 'Transfer to China is not available right now.',
            ]);
        }

        $rate = $this->currentRate();
        $fundingSource = $request->input('funding_source', 'ghs_wallet');
        if (! in_array($fundingSource, ['external', 'rmb_wallet', 'ghs_wallet'], true)) {
            $fundingSource = 'ghs_wallet';
        }

        if ($fundingSource === 'rmb_wallet') {
            return $this->createFromRmbWallet($user, $request, $rate);
        }

        if ($fundingSource === 'ghs_wallet') {
            return $this->createFromGhsWallet($user, $request, $rate);
        }

        if (! $this->hasExternalPaymentMethods()) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'External GHS payment methods are not configured. Pay from your wallet balance instead.',
            ]);
        }

        $validated = $request->validate([
            'ghs_amount' => ['required', 'numeric', 'min:1'],
            'payment_method_id' => ['required', 'integer', 'exists:china_transfer_payment_methods,id'],
        ]);

        $method = ChinaTransferPaymentMethod::query()
            ->where('id', $validated['payment_method_id'])
            ->where('active', true)
            ->first();

        if (! $method) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Choose an active payment method.',
            ]);
        }

        $quote = $this->quote((float) $validated['ghs_amount'], $rate);
        $this->assertLimits($user, $quote, $rate);

        $fields = $this->activeFields();
        $this->validateFields($request, $fields, $method);

        return DB::transaction(function () use ($user, $request, $quote, $rate, $method, $fields) {
            $status = $this->hasPaymentProof($request, $fields, $method)
                ? ChinaTransferStatus::PaymentSubmitted
                : ChinaTransferStatus::PendingPayment;

            $needsApproval = $rate->approval_above_ghs !== null
                && $quote['ghs_amount'] >= (float) $rate->approval_above_ghs;

            $transfer = ChinaTransfer::create([
                'reference' => $this->nextReference(),
                'user_id' => $user->id,
                'status' => $status,
                'ghs_amount' => $quote['ghs_amount'],
                'rmb_amount' => $quote['rmb_amount'],
                'fee_ghs' => $quote['fee_ghs'],
                'total_payable_ghs' => $quote['total_payable_ghs'],
                'ghs_per_rmb' => $quote['ghs_per_rmb'],
                'fee_mode' => $quote['fee_mode'],
                'fee_value' => $quote['fee_value'],
                'rate_id' => $rate->id,
                'payment_method_id' => $method->id,
                'funding_source' => 'external',
                'payment_reference' => $this->fieldText($request, $fields, 'payment_reference'),
                'needs_approval' => $needsApproval,
                'paid_at' => $status === ChinaTransferStatus::PaymentSubmitted ? now() : null,
            ]);

            $this->storeFieldValues($transfer, $request, $fields);
            $this->recordHistory($transfer, null, $status, 'Transfer created', $user->id);
            $this->notifyUser($transfer, $status);
            $this->notifyAdmins($transfer, $status);

            return $transfer->fresh([
                'fieldValues.field',
                'proofs',
                'statusHistory',
                'paymentMethod',
            ]);
        });
    }

    /**
     * Hold RMB from wallet and create an Alipay transfer ticket (rmb-wallet style).
     * Caller must assert KYC + payment PIN before calling.
     */
    private function createFromRmbWallet(User $user, Request $request, ChinaTransferRate $rate): ChinaTransfer
    {
        $validated = $request->validate([
            'rmb_amount' => ['required', 'numeric', 'min:1'],
        ]);

        $rmbAmount = round((float) $validated['rmb_amount'], 2);
        $ghsPerRmb = (float) $rate->ghs_per_rmb;
        if ($ghsPerRmb <= 0) {
            throw ValidationException::withMessages(['rmb_amount' => 'The published rate is invalid.']);
        }

        RmbWalletGuard::assertRmbOutLimits($user, $rmbAmount);

        // Equivalent GHS for limit checks (no fee on wallet-funded transfers).
        $ghsAmount = round($rmbAmount * $ghsPerRmb, 2);
        $quote = [
            'ghs_amount' => $ghsAmount,
            'ghs_per_rmb' => $ghsPerRmb,
            'rmb_amount' => $rmbAmount,
            'fee_ghs' => 0.0,
            'total_payable_ghs' => 0.0,
            'fee_mode' => 'flat',
            'fee_value' => 0.0,
        ];
        $this->assertLimits($user, $quote, $rate);

        $needsApproval = $rate->approval_above_ghs !== null
            && $ghsAmount >= (float) $rate->approval_above_ghs;

        // Recipient fields only — skip payment-proof group for wallet funding.
        $fields = $this->activeFields()->filter(function (ChinaTransferFormField $field) {
            $group = strtolower((string) $field->group);

            return ! in_array($group, ['payment', 'payment_proof', 'proof'], true);
        })->values();
        $this->validateFields($request, $fields, null);

        $meta = RmbWalletGuard::requestMeta($request);

        return DB::transaction(function () use ($user, $request, $quote, $rate, $fields, $rmbAmount, $needsApproval, $meta) {
            try {
                WalletService::ensure($user);
                WalletService::debitRmb(
                    $user,
                    $rmbAmount,
                    'Insufficient RMB balance. Convert GHS to RMB first, then transfer. You need ¥'.number_format($rmbAmount, 2).'.'
                );
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['rmb_amount' => $e->getMessage()]);
            }

            // Large transfers wait for admin approval before processing.
            $status = $needsApproval
                ? ChinaTransferStatus::PaymentVerification
                : ChinaTransferStatus::Processing;

            $transfer = ChinaTransfer::create([
                'reference' => $this->nextReference(),
                'user_id' => $user->id,
                'status' => $status,
                'ghs_amount' => $quote['ghs_amount'],
                'rmb_amount' => $quote['rmb_amount'],
                'fee_ghs' => 0,
                'total_payable_ghs' => 0,
                'ghs_per_rmb' => $quote['ghs_per_rmb'],
                'fee_mode' => 'flat',
                'fee_value' => 0,
                'rate_id' => $rate->id,
                'payment_method_id' => null,
                'funding_source' => 'rmb_wallet',
                'needs_approval' => $needsApproval,
                'ip_address' => $meta['ip_address'],
                'user_agent' => $meta['user_agent'],
                'paid_at' => now(),
                'verified_at' => $needsApproval ? null : now(),
                'processing_at' => $needsApproval ? null : now(),
            ]);

            WalletTransactionService::recordRmbTransferOut(
                $user->id,
                $rmbAmount,
                'CT-'.$transfer->id,
                'RMB transfer held for '.$transfer->reference.' (Alipay)',
            );

            $note = $needsApproval
                ? 'Large wallet transfer — awaiting admin approval'
                : 'Transfer created from RMB wallet';

            $this->storeFieldValues($transfer, $request, $fields);
            $this->recordHistory($transfer, null, $status, $note, $user->id);
            $this->notifyUser($transfer, $status);
            $this->notifyAdmins($transfer, $status);

            return $transfer->fresh([
                'fieldValues.field',
                'proofs',
                'statusHistory',
                'paymentMethod',
            ]);
        });
    }

    /**
     * Debit GHS wallet balance and create Alipay transfer (Buy RMB — no MoMo screenshot).
     */
    private function createFromGhsWallet(User $user, Request $request, ChinaTransferRate $rate): ChinaTransfer
    {
        $validated = $request->validate([
            'ghs_amount' => ['required', 'numeric', 'min:1'],
        ]);

        $quote = $this->quote((float) $validated['ghs_amount'], $rate);
        $this->assertLimits($user, $quote, $rate);

        $needsApproval = $rate->approval_above_ghs !== null
            && $quote['ghs_amount'] >= (float) $rate->approval_above_ghs;

        $fields = $this->activeFields()->filter(function (ChinaTransferFormField $field) {
            $group = strtolower((string) $field->group);

            return ! in_array($group, ['payment', 'payment_proof', 'proof'], true);
        })->values();
        $this->validateFields($request, $fields, null);

        $meta = RmbWalletGuard::requestMeta($request);
        $total = (float) $quote['total_payable_ghs'];

        return DB::transaction(function () use ($user, $request, $quote, $rate, $fields, $needsApproval, $meta, $total) {
            try {
                WalletService::ensure($user);
                WalletService::debitAvailable(
                    $user,
                    $total,
                    'Insufficient wallet balance. You need GH₵'.number_format($total, 2)
                        .' for this Buy RMB transfer. Top up your wallet first.'
                );
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['ghs_amount' => $e->getMessage()]);
            }

            $status = $needsApproval
                ? ChinaTransferStatus::PaymentVerification
                : ChinaTransferStatus::Processing;

            $transfer = ChinaTransfer::create([
                'reference' => $this->nextReference(),
                'user_id' => $user->id,
                'status' => $status,
                'ghs_amount' => $quote['ghs_amount'],
                'rmb_amount' => $quote['rmb_amount'],
                'fee_ghs' => $quote['fee_ghs'],
                'total_payable_ghs' => $quote['total_payable_ghs'],
                'ghs_per_rmb' => $quote['ghs_per_rmb'],
                'fee_mode' => $quote['fee_mode'],
                'fee_value' => $quote['fee_value'],
                'rate_id' => $rate->id,
                'payment_method_id' => null,
                'funding_source' => 'ghs_wallet',
                'needs_approval' => $needsApproval,
                'ip_address' => $meta['ip_address'],
                'user_agent' => $meta['user_agent'],
                'paid_at' => now(),
                'verified_at' => $needsApproval ? null : now(),
                'processing_at' => $needsApproval ? null : now(),
            ]);

            WalletTransactionService::recordChinaTransferDebit(
                $user->id,
                $total,
                'CT-'.$transfer->id,
                'Buy RMB paid from wallet for '.$transfer->reference,
            );

            $note = $needsApproval
                ? 'Paid from GHS wallet — awaiting admin approval'
                : 'Paid from GHS wallet — processing Alipay send';

            $this->storeFieldValues($transfer, $request, $fields);
            $this->recordHistory($transfer, null, $status, $note, $user->id);
            $this->notifyUser($transfer, $status);
            $this->notifyAdmins($transfer, $status);

            return $transfer->fresh([
                'fieldValues.field',
                'proofs',
                'statusHistory',
                'paymentMethod',
            ]);
        });
    }

    public function cancel(ChinaTransfer $transfer, User $actor, ?string $note = null): ChinaTransfer
    {
        $this->assertMutable($transfer);

        $walletFunded = in_array($transfer->funding_source, ['rmb_wallet', 'ghs_wallet'], true);

        if ($actor->id === $transfer->user_id) {
            $allowed = $walletFunded
                ? [ChinaTransferStatus::Processing, ChinaTransferStatus::PaymentVerification]
                : [ChinaTransferStatus::PendingPayment];

            if (! in_array($transfer->status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => $walletFunded
                        ? 'You can only cancel while the transfer is awaiting approval or processing.'
                        : 'You can only cancel before submitting payment proof.',
                ]);
            }
        }

        if (! in_array($transfer->status, [
            ChinaTransferStatus::PendingPayment,
            ChinaTransferStatus::PaymentSubmitted,
            ChinaTransferStatus::PaymentVerification,
            ChinaTransferStatus::Processing,
        ], true)) {
            throw ValidationException::withMessages(['status' => 'This transfer cannot be cancelled.']);
        }

        return DB::transaction(function () use ($transfer, $actor, $note) {
            $this->refundWalletFunding($transfer, 'Transfer cancelled — funds returned to wallet');

            return $this->transition($transfer, ChinaTransferStatus::Cancelled, $actor, $note ?: 'Cancelled', [
                'cancelled_at' => now(),
            ]);
        });
    }

    public function verifyPayment(ChinaTransfer $transfer, User $admin): ChinaTransfer
    {
        $this->assertAdminAction($transfer, [ChinaTransferStatus::PaymentSubmitted]);

        return $this->transition($transfer, ChinaTransferStatus::PaymentVerification, $admin, 'Payment verified', [
            'verified_at' => now(),
            'assigned_admin_id' => $admin->id,
        ]);
    }

    public function rejectPayment(ChinaTransfer $transfer, User $admin, string $reason): ChinaTransfer
    {
        $this->assertAdminAction($transfer, [
            ChinaTransferStatus::PaymentSubmitted,
            ChinaTransferStatus::PaymentVerification,
            ChinaTransferStatus::Processing,
        ]);

        return DB::transaction(function () use ($transfer, $admin, $reason) {
            $this->refundWalletFunding($transfer, 'Transfer rejected — funds returned to wallet');

            return $this->transition($transfer, ChinaTransferStatus::PaymentRejected, $admin, $reason, [
                'rejection_reason' => $reason,
                'assigned_admin_id' => $admin->id,
            ]);
        });
    }

    public function startProcessing(ChinaTransfer $transfer, User $admin): ChinaTransfer
    {
        $this->assertAdminAction($transfer, [
            ChinaTransferStatus::PaymentVerification,
            ChinaTransferStatus::PaymentSubmitted,
        ]);

        return $this->transition($transfer, ChinaTransferStatus::Processing, $admin, 'Processing RMB transfer', [
            'processing_at' => now(),
            'assigned_admin_id' => $admin->id,
            'verified_at' => $transfer->verified_at ?? now(),
            'needs_approval' => false,
        ]);
    }

    public function markSent(ChinaTransfer $transfer, User $admin, Request $request): ChinaTransfer
    {
        $this->assertAdminAction($transfer, [ChinaTransferStatus::Processing]);

        $validated = $request->validate([
            'rmb_sent_amount' => ['nullable', 'numeric', 'min:0.01'],
            'rmb_transfer_ref' => ['nullable', 'string', 'max:120'],
            'rmb_sent_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'proof' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);

        $sentAmount = isset($validated['rmb_sent_amount']) && (float) $validated['rmb_sent_amount'] > 0
            ? (float) $validated['rmb_sent_amount']
            : (float) $transfer->rmb_amount;

        if ($sentAmount <= 0) {
            throw ValidationException::withMessages([
                'rmb_sent_amount' => 'This transfer has no RMB amount to mark as sent.',
            ]);
        }

        $file = $request->file('proof');
        $path = $file->store('china-transfers/'.$transfer->id.'/rmb-proof', 'public');

        ChinaTransferProof::create([
            'china_transfer_id' => $transfer->id,
            'type' => 'rmb_sent',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'note' => $validated['note'] ?? null,
            'uploaded_by' => $admin->id,
        ]);

        return $this->transition($transfer, ChinaTransferStatus::RmbSent, $admin, $validated['note'] ?? 'RMB sent', [
            'rmb_sent_amount' => round($sentAmount, 2),
            'rmb_transfer_ref' => $validated['rmb_transfer_ref'] ?? null,
            'rmb_sent_at' => $validated['rmb_sent_at'] ?? now(),
            'sent_at' => now(),
            'assigned_admin_id' => $admin->id,
        ]);
    }

    public function complete(ChinaTransfer $transfer, User $admin): ChinaTransfer
    {
        $this->assertAdminAction($transfer, [ChinaTransferStatus::RmbSent]);

        if (! $transfer->proofs()->where('type', 'rmb_sent')->exists()) {
            throw ValidationException::withMessages([
                'proof' => 'Upload RMB transfer proof before completing.',
            ]);
        }

        return $this->transition($transfer, ChinaTransferStatus::Completed, $admin, 'Completed', [
            'completed_at' => now(),
            'assigned_admin_id' => $admin->id,
        ]);
    }

    public function uploadProofAndComplete(ChinaTransfer $transfer, User $admin, Request $request): ChinaTransfer
    {
        return DB::transaction(function () use ($transfer, $admin, $request) {
            $transfer = $transfer->fresh(['proofs']);

            if (in_array($transfer->status, [
                ChinaTransferStatus::PaymentSubmitted,
                ChinaTransferStatus::PaymentVerification,
            ], true)) {
                if ($transfer->status === ChinaTransferStatus::PaymentSubmitted) {
                    $transfer = $this->verifyPayment($transfer->fresh(), $admin);
                }
                $transfer = $this->startProcessing($transfer->fresh(), $admin);
            }

            $transfer = $transfer->fresh(['proofs']);

            if ($transfer->status === ChinaTransferStatus::Processing) {
                $transfer = $this->markSent($transfer, $admin, $request);
            } elseif ($transfer->status === ChinaTransferStatus::RmbSent) {
                if (! $transfer->proofs()->where('type', 'rmb_sent')->exists()) {
                    throw ValidationException::withMessages([
                        'proof' => 'Upload RMB transfer proof before completing.',
                    ]);
                }
            } else {
                throw ValidationException::withMessages([
                    'status' => 'This transfer cannot be completed from its current status.',
                ]);
            }

            return $this->complete($transfer->fresh(['proofs']), $admin);
        });
    }

    public function fail(ChinaTransfer $transfer, User $admin, string $reason): ChinaTransfer
    {
        $this->assertAdminAction($transfer, [
            ChinaTransferStatus::Processing,
            ChinaTransferStatus::RmbSent,
        ]);

        return DB::transaction(function () use ($transfer, $admin, $reason) {
            $this->refundWalletFunding($transfer, 'Transfer failed — funds returned to wallet');

            return $this->transition($transfer, ChinaTransferStatus::TransferFailed, $admin, $reason, [
                'rejection_reason' => $reason,
            ]);
        });
    }

    /**
     * Refund wallet funding once when cancelled / rejected / failed.
     */
    private function refundWalletFunding(ChinaTransfer $transfer, string $description): void
    {
        if ($transfer->funding_source === 'ghs_wallet') {
            $this->refundGhsWalletHold($transfer, $description);

            return;
        }

        $this->refundRmbWalletHold($transfer, $description);
    }

    private function refundGhsWalletHold(ChinaTransfer $transfer, string $description): void
    {
        if ($transfer->funding_source !== 'ghs_wallet' || $transfer->rmb_wallet_refunded) {
            return;
        }

        $amount = (float) $transfer->total_payable_ghs;
        if ($amount <= 0) {
            $transfer->rmb_wallet_refunded = true;
            $transfer->save();

            return;
        }

        $user = $transfer->user ?? User::query()->find($transfer->user_id);
        if (! $user) {
            return;
        }

        $wallet = \App\Models\Wallet::where('user_id', $user->id)->lockForUpdate()->first()
            ?? WalletService::ensure($user);
        $wallet->increment('available_balance', $amount);

        WalletTransactionService::recordChinaTransferRefund(
            $user->id,
            $amount,
            'CT-'.$transfer->id,
            $description.' ('.$transfer->reference.')',
        );

        $transfer->rmb_wallet_refunded = true;
        $transfer->save();
    }

    /**
     * Refund held RMB once when a wallet-funded transfer is cancelled / rejected / failed.
     */
    private function refundRmbWalletHold(ChinaTransfer $transfer, string $description): void
    {
        if ($transfer->funding_source !== 'rmb_wallet' || $transfer->rmb_wallet_refunded) {
            return;
        }

        $amount = (float) $transfer->rmb_amount;
        if ($amount <= 0) {
            $transfer->rmb_wallet_refunded = true;
            $transfer->save();

            return;
        }

        $user = $transfer->user ?? User::query()->find($transfer->user_id);
        if (! $user) {
            return;
        }

        WalletService::creditRmb($user, $amount);
        WalletTransactionService::recordRmbTransferRefund(
            $user->id,
            $amount,
            'CT-'.$transfer->id,
            $description.' ('.$transfer->reference.')',
        );

        $transfer->rmb_wallet_refunded = true;
        $transfer->save();
    }

    public function addNote(ChinaTransfer $transfer, User $admin, string $note): ChinaTransferAdminNote
    {
        return ChinaTransferAdminNote::create([
            'china_transfer_id' => $transfer->id,
            'admin_id' => $admin->id,
            'note' => $note,
        ]);
    }

    public function publishRate(User $admin, array $data): ChinaTransferRate
    {
        return DB::transaction(function () use ($admin, $data) {
            ChinaTransferRate::query()
                ->where('active', true)
                ->whereNull('effective_to')
                ->update([
                    'active' => false,
                    'effective_to' => now(),
                ]);

            return ChinaTransferRate::create([
                'ghs_per_rmb' => $data['ghs_per_rmb'],
                'fee_mode' => $data['fee_mode'] ?? 'flat',
                'fee_value' => $data['fee_value'] ?? 0,
                'min_ghs' => $data['min_ghs'] ?? 50,
                'max_ghs' => $data['max_ghs'] ?? 50000,
                'daily_max_ghs' => $data['daily_max_ghs'] ?? null,
                'monthly_max_ghs' => $data['monthly_max_ghs'] ?? null,
                'max_per_day' => $data['max_per_day'] ?? null,
                'approval_above_ghs' => $data['approval_above_ghs'] ?? null,
                'active' => true,
                'effective_from' => $data['effective_from'] ?? now(),
                'created_by' => $admin->id,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function configPayload(): array
    {
        $settings = $this->settings();
        $rate = $this->currentRate();
        $quote = $rate ? $this->quote((float) $rate->min_ghs, $rate) : null;

        return [
            'enabled' => $this->isOpen(),
            'external_enabled' => $this->isExternalOpen(),
            'wallet_funding_enabled' => $this->isOpen(),
            'channel' => 'alipay',
            'channel_label' => 'Alipay',
            'instructions' => $settings->instructions,
            'transfer_hours' => $this->transferHoursPayload(),
            'rate' => $rate ? $this->ratePayload($rate) : null,
            'sample_quote' => $quote,
            'payment_methods' => ChinaTransferPaymentMethod::query()
                ->where('active', true)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ChinaTransferPaymentMethod $m) => $this->methodPayload($m))
                ->values()
                ->all(),
            'fields' => $this->activeFields()
                ->map(fn (ChinaTransferFormField $f) => $this->fieldPayload($f))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function transferPayload(ChinaTransfer $transfer, bool $forAdmin = false): array
    {
        $transfer->loadMissing([
            'user:id,name,email,mobile',
            'paymentMethod',
            'fieldValues.field',
            'proofs',
            'statusHistory.actor:id,name',
            'adminNotes.admin:id,name',
            'assignedAdmin:id,name',
        ]);

        $quote = [
            'ghs_amount' => (float) $transfer->ghs_amount,
            'ghs_per_rmb' => (float) $transfer->ghs_per_rmb,
            'rmb_per_ghs' => (float) $transfer->ghs_per_rmb > 0 ? round(1 / (float) $transfer->ghs_per_rmb, 6) : 0,
            'rmb_amount' => (float) $transfer->rmb_amount,
            'fee_ghs' => (float) $transfer->fee_ghs,
            'total_payable_ghs' => (float) $transfer->total_payable_ghs,
            'fee_mode' => $transfer->fee_mode,
            'fee_value' => (float) $transfer->fee_value,
            'breakdown' => [
                'send' => 'GH₵'.number_format((float) $transfer->ghs_amount, 2),
                'rate' => '1 RMB = GH₵'.number_format((float) $transfer->ghs_per_rmb, 4),
                'rate_ghs' => '1 GHS → ¥'.number_format((float) $transfer->ghs_per_rmb > 0 ? 1 / (float) $transfer->ghs_per_rmb : 0, 3),
                'rmb' => '¥'.number_format((float) $transfer->rmb_amount, 2),
                'fee' => 'GH₵'.number_format((float) $transfer->fee_ghs, 2),
                'total' => 'GH₵'.number_format((float) $transfer->total_payable_ghs, 2),
            ],
        ];

        $payload = [
            'id' => $transfer->id,
            'reference' => $transfer->reference,
            'status' => $transfer->status->value,
            'status_label' => $transfer->status->label(),
            'funding_source' => $transfer->funding_source ?? 'external',
            'funding_source_label' => match ($transfer->funding_source ?? 'external') {
                'ghs_wallet' => 'GHS wallet',
                'rmb_wallet' => 'RMB wallet',
                default => 'External GHS payment',
            },
            'quote' => $quote,
            'channel' => 'alipay',
            'needs_approval' => $transfer->needs_approval,
            'payment_method' => $transfer->paymentMethod ? $this->methodPayload($transfer->paymentMethod) : null,
            'payment_reference' => $transfer->payment_reference,
            'payment_proof_url' => $transfer->paymentProofUrl(),
            'rejection_reason' => $transfer->rejection_reason,
            'rmb_sent_amount' => $transfer->rmb_sent_amount !== null ? (float) $transfer->rmb_sent_amount : null,
            'rmb_transfer_ref' => $transfer->rmb_transfer_ref,
            'rmb_sent_at' => $transfer->rmb_sent_at?->toIso8601String(),
            'created_at' => $transfer->created_at?->toIso8601String(),
            'paid_at' => $transfer->paid_at?->toIso8601String(),
            'verified_at' => $transfer->verified_at?->toIso8601String(),
            'processing_at' => $transfer->processing_at?->toIso8601String(),
            'sent_at' => $transfer->sent_at?->toIso8601String(),
            'completed_at' => $transfer->completed_at?->toIso8601String(),
            'cancelled_at' => $transfer->cancelled_at?->toIso8601String(),
            'can_cancel' => in_array($transfer->funding_source ?? 'external', ['rmb_wallet', 'ghs_wallet'], true)
                ? in_array($transfer->status, [
                    ChinaTransferStatus::Processing,
                    ChinaTransferStatus::PaymentVerification,
                ], true)
                : $transfer->status === ChinaTransferStatus::PendingPayment,
            'can_upload_proof_and_complete' => in_array($transfer->status, [
                ChinaTransferStatus::PaymentSubmitted,
                ChinaTransferStatus::PaymentVerification,
                ChinaTransferStatus::Processing,
            ], true),
            'timeline' => $this->timelinePayload($transfer),
            'fields' => $transfer->fieldValues->map(fn (ChinaTransferFieldValue $v) => [
                'id' => $v->id,
                'field_id' => $v->field_id,
                'name' => $v->field?->name,
                'label' => $v->field?->label,
                'group' => $v->field?->group,
                'type' => $v->field?->type,
                'value' => $v->value,
                'file_url' => $v->fileUrl(),
            ])->values()->all(),
            'proofs' => $transfer->proofs->map(fn (ChinaTransferProof $p) => [
                'id' => $p->id,
                'type' => $p->type,
                'url' => $p->url(),
                'original_name' => $p->original_name,
                'mime' => $p->mime,
                'note' => $p->note,
                'created_at' => $p->created_at?->toIso8601String(),
            ])->values()->all(),
            'history' => $transfer->statusHistory->map(fn (ChinaTransferStatusHistory $h) => [
                'from' => $h->from_status?->value,
                'to' => $h->to_status->value,
                'to_label' => $h->to_status->label(),
                'note' => $h->note,
                'actor' => $h->actor?->name,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->values()->all(),
            'wallet_receipt' => $this->walletReceiptPayload($transfer),
        ];

        if ($forAdmin) {
            $payload['user'] = $transfer->user ? [
                'id' => $transfer->user->id,
                'name' => $transfer->user->name,
                'email' => $transfer->user->email,
                'mobile' => $transfer->user->mobile,
            ] : null;
            $payload['ip_address'] = $transfer->ip_address;
            $payload['user_agent'] = $transfer->user_agent;
            $payload['assigned_admin'] = $transfer->assignedAdmin?->name;
            $payload['admin_notes'] = $transfer->adminNotes->map(fn (ChinaTransferAdminNote $n) => [
                'id' => $n->id,
                'note' => $n->note,
                'admin' => $n->admin?->name,
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $base = ChinaTransfer::query();

        return [
            'total' => (clone $base)->count(),
            'pending_payment' => (clone $base)->where('status', ChinaTransferStatus::PendingPayment)->count(),
            'awaiting_verification' => (clone $base)->whereIn('status', [
                ChinaTransferStatus::PaymentSubmitted,
                ChinaTransferStatus::PaymentVerification,
            ])->count(),
            'processing' => (clone $base)->whereIn('status', [
                ChinaTransferStatus::Processing,
                ChinaTransferStatus::RmbSent,
            ])->count(),
            'completed' => (clone $base)->where('status', ChinaTransferStatus::Completed)->count(),
            'failed' => (clone $base)->whereIn('status', [
                ChinaTransferStatus::PaymentFailed,
                ChinaTransferStatus::PaymentRejected,
                ChinaTransferStatus::TransferFailed,
                ChinaTransferStatus::Cancelled,
            ])->count(),
            'ghs_received' => (float) (clone $base)->where('status', ChinaTransferStatus::Completed)->sum('total_payable_ghs'),
            'rmb_sent' => (float) (clone $base)->where('status', ChinaTransferStatus::Completed)->sum('rmb_sent_amount'),
            'fees_collected' => (float) (clone $base)->where('status', ChinaTransferStatus::Completed)->sum('fee_ghs'),
            'today' => (clone $base)->whereDate('created_at', today())->count(),
            'this_month' => (clone $base)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
        ];
    }

    public function pendingAdminCount(): int
    {
        return ChinaTransfer::query()
            ->whereIn('status', [
                ChinaTransferStatus::PaymentSubmitted,
                ChinaTransferStatus::PaymentVerification,
                ChinaTransferStatus::Processing,
                ChinaTransferStatus::RmbSent,
            ])
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function ratePayload(ChinaTransferRate $rate): array
    {
        return [
            'id' => $rate->id,
            'ghs_per_rmb' => $rate->effectiveGhsPerRmb(),
            'rmb_per_ghs' => $rate->rmbPerGhs(),
            'fee_mode' => $rate->fee_mode,
            'fee_value' => (float) $rate->fee_value,
            'min_ghs' => (float) $rate->min_ghs,
            'max_ghs' => (float) $rate->max_ghs,
            'daily_max_ghs' => $rate->daily_max_ghs !== null ? (float) $rate->daily_max_ghs : null,
            'monthly_max_ghs' => $rate->monthly_max_ghs !== null ? (float) $rate->monthly_max_ghs : null,
            'max_per_day' => $rate->max_per_day,
            'approval_above_ghs' => $rate->approval_above_ghs !== null ? (float) $rate->approval_above_ghs : null,
            'active' => $rate->active,
            'effective_from' => $rate->effective_from?->toIso8601String(),
            'effective_to' => $rate->effective_to?->toIso8601String(),
            'updated_at' => $rate->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function methodPayload(ChinaTransferPaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'name' => $method->name,
            'type' => $method->type,
            'account_name' => $method->account_name,
            'account_number' => $method->account_number,
            'bank_name' => $method->bank_name,
            'network' => $method->network,
            'instructions' => $method->instructions,
            'qr_url' => $method->qrUrl(),
            'proof_required' => $method->proof_required,
            'sort_order' => $method->sort_order,
            'active' => $method->active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fieldPayload(ChinaTransferFormField $field): array
    {
        return [
            'id' => $field->id,
            'group' => $field->group,
            'type' => $field->type,
            'name' => $field->name,
            'label' => $field->label,
            'placeholder' => $field->placeholder,
            'help_text' => $field->help_text,
            'required' => $field->required,
            'options' => $field->options ?? [],
            'file_types' => $field->file_types ?? [],
            'max_size_kb' => $field->max_size_kb,
            'sort_order' => $field->sort_order,
            'active' => $field->active,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, ChinaTransferFormField>
     */
    public function activeFields()
    {
        return ChinaTransferFormField::query()
            ->where('active', true)
            ->orderBy('group')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @param  list<ChinaTransferStatus>  $allowed
     */
    private function assertAdminAction(ChinaTransfer $transfer, array $allowed): void
    {
        $this->assertMutable($transfer);

        if (! in_array($transfer->status, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'This action is not available for '.$transfer->status->label().'.',
            ]);
        }
    }

    private function assertMutable(ChinaTransfer $transfer): void
    {
        if ($transfer->status->isImmutable()) {
            throw ValidationException::withMessages([
                'status' => 'Completed transfers cannot be edited. Create an adjustment or refund instead.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function assertLimits(User $user, array $quote, ChinaTransferRate $rate): void
    {
        $ghs = $quote['ghs_amount'];

        if ($ghs < (float) $rate->min_ghs) {
            throw ValidationException::withMessages([
                'ghs_amount' => 'Minimum transfer is GH₵'.number_format((float) $rate->min_ghs, 2).'.',
            ]);
        }

        if ($ghs > (float) $rate->max_ghs) {
            throw ValidationException::withMessages([
                'ghs_amount' => 'Maximum per transfer is GH₵'.number_format((float) $rate->max_ghs, 2).'.',
            ]);
        }

        $openStatuses = [
            ChinaTransferStatus::PendingPayment,
            ChinaTransferStatus::PaymentSubmitted,
            ChinaTransferStatus::PaymentVerification,
            ChinaTransferStatus::Processing,
            ChinaTransferStatus::RmbSent,
            ChinaTransferStatus::Completed,
        ];

        if ($rate->max_per_day) {
            $todayCount = ChinaTransfer::query()
                ->where('user_id', $user->id)
                ->whereDate('created_at', today())
                ->whereNotIn('status', [
                    ChinaTransferStatus::Cancelled,
                    ChinaTransferStatus::PaymentRejected,
                    ChinaTransferStatus::PaymentFailed,
                    ChinaTransferStatus::TransferFailed,
                ])
                ->count();

            if ($todayCount >= $rate->max_per_day) {
                throw ValidationException::withMessages([
                    'ghs_amount' => 'You have reached the daily transfer limit.',
                ]);
            }
        }

        if ($rate->daily_max_ghs) {
            $todaySum = (float) ChinaTransfer::query()
                ->where('user_id', $user->id)
                ->whereDate('created_at', today())
                ->whereIn('status', $openStatuses)
                ->sum('ghs_amount');

            if ($todaySum + $ghs > (float) $rate->daily_max_ghs) {
                throw ValidationException::withMessages([
                    'ghs_amount' => 'This amount exceeds your daily China Transfer limit.',
                ]);
            }
        }

        if ($rate->monthly_max_ghs) {
            $monthSum = (float) ChinaTransfer::query()
                ->where('user_id', $user->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->whereIn('status', $openStatuses)
                ->sum('ghs_amount');

            if ($monthSum + $ghs > (float) $rate->monthly_max_ghs) {
                throw ValidationException::withMessages([
                    'ghs_amount' => 'This amount exceeds your monthly China Transfer limit.',
                ]);
            }
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ChinaTransferFormField>  $fields
     */
    private function validateFields(Request $request, $fields, ?ChinaTransferPaymentMethod $method): void
    {
        $rules = [];

        foreach ($fields as $field) {
            $key = $field->isFile() ? 'files.'.$field->id : 'fields.'.$field->id;
            $required = $field->required ? 'required' : 'nullable';

            if ($field->name === 'payment_screenshot' && $method?->proof_required) {
                $required = 'required';
            }

            // Buy RMB (rmb-wallet): QR required; account / name / notes optional.
            if (strtolower((string) $field->group) === 'recipient') {
                $blob = strtolower($field->name.' '.$field->label);
                $isQr = $field->isFile() || str_contains($blob, 'qr');
                $required = $isQr ? 'required' : 'nullable';
            }

            $rules[$key] = match ($field->type) {
                'number' => [$required, 'numeric'],
                'email' => [$required, 'email', 'max:190'],
                'phone' => [$required, 'string', 'max:40'],
                'textarea' => [$required, 'string', 'max:2000'],
                'date' => [$required, 'date'],
                'dropdown', 'radio' => array_filter([$required, 'string', $field->options ? 'in:'.implode(',', $field->options) : null]),
                'checkbox' => ['sometimes'],
                'image' => [$required, 'image', 'max:'.($field->max_size_kb ?: 5120)],
                'document' => [$required, 'file', 'max:'.($field->max_size_kb ?: 8192), 'mimes:pdf,jpg,jpeg,png,webp'],
                'files' => [$required, 'array'],
                default => [$required, 'string', 'max:255'],
            };

            if ($field->type === 'files') {
                $rules[$key.'.*'] = ['file', 'max:'.($field->max_size_kb ?: 5120)];
            }
        }

        $request->validate($rules);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ChinaTransferFormField>  $fields
     */
    private function hasPaymentProof(Request $request, $fields, ChinaTransferPaymentMethod $method): bool
    {
        if (! $method->proof_required) {
            return filled($this->fieldText($request, $fields, 'payment_reference'));
        }

        $proofField = $fields->first(fn (ChinaTransferFormField $f) => $f->name === 'payment_screenshot' || ($f->group === 'payment' && $f->isFile()));

        return $proofField ? $request->hasFile('files.'.$proofField->id) : $request->hasFile('files');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ChinaTransferFormField>  $fields
     */
    private function fieldText(Request $request, $fields, string $name): ?string
    {
        $field = $fields->firstWhere('name', $name);
        if (! $field) {
            return null;
        }

        $value = $request->input('fields.'.$field->id);

        return filled($value) ? trim((string) $value) : null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ChinaTransferFormField>  $fields
     */
    private function storeFieldValues(ChinaTransfer $transfer, Request $request, $fields): void
    {
        foreach ($fields as $field) {
            if ($field->isFile()) {
                $uploaded = $request->file('files.'.$field->id);
                if ($uploaded instanceof UploadedFile) {
                    $path = $uploaded->store('china-transfers/'.$transfer->id.'/fields', 'public');
                    ChinaTransferFieldValue::create([
                        'china_transfer_id' => $transfer->id,
                        'field_id' => $field->id,
                        'file_path' => $path,
                    ]);
                    if ($field->name === 'payment_screenshot') {
                        $transfer->update(['payment_proof_path' => $path]);
                    }
                } elseif (is_array($uploaded)) {
                    $paths = [];
                    foreach ($uploaded as $file) {
                        if ($file instanceof UploadedFile) {
                            $paths[] = $file->store('china-transfers/'.$transfer->id.'/fields', 'public');
                        }
                    }
                    if ($paths !== []) {
                        ChinaTransferFieldValue::create([
                            'china_transfer_id' => $transfer->id,
                            'field_id' => $field->id,
                            'value' => json_encode($paths),
                            'file_path' => $paths[0],
                        ]);
                    }
                }

                continue;
            }

            $value = $request->input('fields.'.$field->id);
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $value = json_encode($value);
            }

            ChinaTransferFieldValue::create([
                'china_transfer_id' => $transfer->id,
                'field_id' => $field->id,
                'value' => (string) $value,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function transition(
        ChinaTransfer $transfer,
        ChinaTransferStatus $to,
        User $actor,
        ?string $note = null,
        array $attributes = [],
    ): ChinaTransfer {
        $from = $transfer->status;

        $transfer->fill($attributes);
        $transfer->status = $to;
        $transfer->save();

        $this->recordHistory($transfer, $from, $to, $note, $actor->id);
        $this->notifyUser($transfer->fresh(), $to);

        return $transfer->fresh([
            'fieldValues.field',
            'proofs',
            'statusHistory',
            'paymentMethod',
            'adminNotes',
        ]);
    }

    private function recordHistory(
        ChinaTransfer $transfer,
        ?ChinaTransferStatus $from,
        ChinaTransferStatus $to,
        ?string $note,
        ?int $actorId,
    ): void {
        ChinaTransferStatusHistory::create([
            'china_transfer_id' => $transfer->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function timelinePayload(ChinaTransfer $transfer): array
    {
        $current = $transfer->status;
        $failed = $current->isTerminal() && $current !== ChinaTransferStatus::Completed;

        return collect(ChinaTransferStatus::timeline())->map(function (ChinaTransferStatus $step) use ($transfer, $current, $failed) {
            $reached = false;
            foreach (ChinaTransferStatus::timeline() as $item) {
                if ($item === $step) {
                    $reached = true;
                    break;
                }
                if ($item === $current) {
                    break;
                }
            }

            if ($current === ChinaTransferStatus::Completed) {
                $reached = true;
            }

            return [
                'key' => $step->value,
                'label' => $step->label(),
                'done' => $reached && $current !== $step && ! $failed,
                'current' => $current === $step,
                'failed' => $failed && $current === $step,
            ];
        })->values()->all();
    }

    private function nextReference(): string
    {
        $date = now()->format('Ymd');
        $count = ChinaTransfer::query()
            ->where('reference', 'like', "CN-{$date}-%")
            ->lockForUpdate()
            ->count() + 1;

        return sprintf('CN-%s-%05d', $date, $count);
    }

    private function notifyUser(ChinaTransfer $transfer, ChinaTransferStatus $status): void
    {
        // Buyers are already notified at RMB sent (with proof). Skip the extra "complete" SMS/push.
        if ($status === ChinaTransferStatus::Completed) {
            return;
        }

        $transfer->loadMissing('user');
        if (! $transfer->user) {
            return;
        }

        try {
            AppNotificationService::send(
                $transfer->user,
                'china_transfer',
                $this->userTitle($status),
                $this->userBody($transfer, $status),
                [
                    'transfer_id' => $transfer->id,
                    'reference' => $transfer->reference,
                    'status' => $status->value,
                    'url' => '/wallet/china-transfer/'.$transfer->id,
                ],
            );
            $transfer->user->notify(new ChinaTransferUserNotification($transfer, $status));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyAdmins(ChinaTransfer $transfer, ChinaTransferStatus $status): void
    {
        try {
            AdminNotifier::notify(new ChinaTransferAdminNotification($transfer, $status));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function userTitle(ChinaTransferStatus $status): string
    {
        return match ($status) {
            ChinaTransferStatus::PendingPayment => 'China Transfer created',
            ChinaTransferStatus::PaymentSubmitted => 'Payment submitted',
            ChinaTransferStatus::PaymentVerification => 'Payment received',
            ChinaTransferStatus::Processing => 'Transfer processing',
            ChinaTransferStatus::RmbSent => 'RMB sent',
            ChinaTransferStatus::Completed => 'China Transfer completed',
            ChinaTransferStatus::PaymentRejected => 'Payment rejected',
            ChinaTransferStatus::Cancelled => 'Transfer cancelled',
            ChinaTransferStatus::TransferFailed => 'Transfer failed',
            default => 'China Transfer update',
        };
    }

    private function userBody(ChinaTransfer $transfer, ChinaTransferStatus $status): string
    {
        return match ($status) {
            ChinaTransferStatus::RmbSent, ChinaTransferStatus::Completed => "{$transfer->reference}: ¥"
                .number_format((float) ($transfer->rmb_sent_amount ?? $transfer->rmb_amount), 2)
                .' sent to your Alipay.',
            ChinaTransferStatus::PaymentRejected => $transfer->rejection_reason
                ?: 'Your GHS payment could not be verified.',
            default => "{$transfer->reference} is now {$status->label()}.",
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function walletReceiptPayload(ChinaTransfer $transfer): ?array
    {
        if (! in_array($transfer->funding_source, ['ghs_wallet', 'rmb_wallet'], true)) {
            return null;
        }

        $transaction = WalletTransaction::query()
            ->where('user_id', $transfer->user_id)
            ->where('reference', 'CT-'.$transfer->id)
            ->whereIn('type', [
                WalletTransactionType::ChinaTransferDebit,
                WalletTransactionType::RmbTransferOut,
            ])
            ->orderBy('id')
            ->first();

        if (! $transaction) {
            return null;
        }

        $user = $transfer->user ?? User::query()->find($transfer->user_id);
        if (! $user) {
            return null;
        }

        $wallet = WalletService::ensure($user);
        $balances = WalletTransactionService::balancesAfterTransaction(
            $transaction,
            (float) $wallet->available_balance,
            (float) $wallet->pending_balance,
            (float) $wallet->rmb_balance,
        );

        $currency = strtoupper((string) ($transaction->currency ?? 'GHS'));

        return [
            'wallet_transaction_id' => $transaction->id,
            'currency' => $currency,
            'amount' => round(abs((float) $transaction->amount), 2),
            'type_label' => $transaction->type->label(),
            'debited_at' => $transaction->created_at?->toIso8601String(),
            'balance_before' => $balances['balance_before'],
            'balance_after' => $balances['balance_after'],
            'rmb_before' => $balances['rmb_before'],
            'rmb_after' => $balances['rmb_after'],
        ];
    }
}
