<?php

namespace App\Services;

use App\Enums\WithdrawalStatus;
use App\Models\SellerPayoutMethod;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalRejectedNotification;
use App\Notifications\WithdrawalRequestedNotification;
use App\Support\GhanaBanks;
use App\Support\PaymentReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalPayoutService
{
    public function __construct(
        private PaystackService $paystack,
        private JunipayService $junipay,
    ) {}

    /**
     * Send a pending withdrawal through Junipay. The reference is saved before the call,
     * so a retry after a timeout reuses it and Junipay can refuse the duplicate.
     *
     * @return array{otp_required: bool, transfer_code: string|null, message: string}
     */
    public function processJunipay(Withdrawal $withdrawal, ?User $admin): array
    {
        if (! $this->junipay->payoutsAvailable()) {
            throw new \RuntimeException('Junipay payouts are not set up or are turned off.');
        }

        $withdrawal = DB::transaction(function () use ($withdrawal) {
            $withdrawal = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! in_array($withdrawal->status, [WithdrawalStatus::Pending, WithdrawalStatus::Processing], true)) {
                throw new \RuntimeException('This withdrawal can no longer be paid out.');
            }
            if ($withdrawal->status === WithdrawalStatus::Processing && filled($withdrawal->paystack_reference)) {
                throw new \RuntimeException('This withdrawal was already sent to Paystack.');
            }
            if (filled($withdrawal->junipay_trans_id) && $withdrawal->junipay_status !== 'failed') {
                throw new \RuntimeException('This withdrawal was already sent to Junipay. Wait for confirmation.');
            }

            if ($withdrawal->junipay_status === 'failed') {
                $withdrawal->update([
                    'junipay_reference' => null,
                    'junipay_trans_id' => null,
                    'junipay_status' => null,
                    'failure_reason' => null,
                ]);
            }

            $reference = (string) $withdrawal->junipay_reference;
            if ($reference === '' || ! preg_match('/^[A-Z]+$/', $reference)) {
                $withdrawal->update(['junipay_reference' => JunipayService::payoutReference((int) $withdrawal->id)]);
            }

            return $withdrawal;
        });

        $withdrawal->loadMissing('user');
        $transfer = $this->junipay->transfer(
            (string) $withdrawal->junipay_reference,
            (float) $withdrawal->amount,
            (string) $withdrawal->network,
            (string) $withdrawal->momo_number,
            trim((string) ($withdrawal->account_name ?: $withdrawal->user?->name)),
            'CityShop withdrawal #'.$withdrawal->id,
            (string) $withdrawal->user?->mobile,
        );

        $withdrawal->update([
            'status' => WithdrawalStatus::Processing,
            'junipay_trans_id' => $transfer['trans_id'],
            'junipay_status' => $transfer['status'],
            'payout_channel' => 'junipay',
            'failure_reason' => null,
            'processed_by' => $admin?->id ?? $withdrawal->processed_by,
        ]);

        if ($transfer['status'] === 'success') {
            $this->markAsPaid($withdrawal->fresh(), 'junipay');

            return ['otp_required' => false, 'transfer_code' => $transfer['trans_id'], 'message' => 'Payout sent successfully via Junipay.'];
        }

        return [
            'otp_required' => false,
            'transfer_code' => $transfer['trans_id'],
            'message' => 'Payout sent to Junipay. It completes when Junipay confirms the transfer.',
        ];
    }

    /**
     * Junipay callback for a payout. The transaction ID must be the one Junipay gave us,
     * and a failure is only applied after Junipay confirms it, because failing refunds the wallet.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleJunipayCallback(Withdrawal $withdrawal, array $data): void
    {
        if ($withdrawal->status !== WithdrawalStatus::Processing || $withdrawal->payout_channel !== 'junipay') {
            return;
        }

        $info = is_array($data['info'] ?? null) ? $data['info'] : [];
        $known = array_values(array_filter([(string) $withdrawal->junipay_trans_id, (string) $withdrawal->junipay_reference]));
        $candidates = array_values(array_unique(array_filter(array_map(
            fn ($value) => trim((string) $value),
            [
                $data['trans_id'] ?? '',
                $data['transID'] ?? '',
                $data['foreignID'] ?? '',
                $data['partnerTransID'] ?? '',
                $info['trans_id'] ?? '',
                $info['transID'] ?? '',
                $info['foreignID'] ?? '',
                $info['partnerTransID'] ?? '',
            ],
        ), fn (string $value) => $value !== '')));
        if ($known === [] || array_intersect($candidates, $known) === []) {
            Log::warning('Junipay payout callback ignored: transaction ID does not match', [
                'withdrawal_id' => $withdrawal->id,
            ]);

            return;
        }

        $lookup = filled($withdrawal->junipay_trans_id)
            ? (string) $withdrawal->junipay_trans_id
            : (string) $withdrawal->junipay_reference;
        $claimed = strtolower(trim((string) ($data['status'] ?? $info['status'] ?? '')));
        try {
            $status = $this->junipay->transactionStatus($lookup);
        } catch (\Throwable $e) {
            Log::warning('Junipay payout status check failed', ['withdrawal_id' => $withdrawal->id, 'error' => $e->getMessage()]);
            $status = in_array($claimed, ['success', 'successful', 'completed', 'paid'], true) ? 'success' : 'pending';
        }

        $message = $data['message'] ?? $info['message'] ?? $info['description'] ?? '';
        if (is_array($message)) {
            $message = implode(' ', array_filter(array_map(fn ($value) => is_scalar($value) ? trim((string) $value) : '', $message)));
        }
        $this->applyJunipayStatus($withdrawal, $status, is_scalar($message) ? trim((string) $message) : '');
    }

    /**
     * @return array{checked: int, paid: int, failed: int, skipped: int}
     */
    public function reconcilePendingJunipayTransfers(int $limit = 50): array
    {
        $stats = ['checked' => 0, 'paid' => 0, 'failed' => 0, 'skipped' => 0];
        if (! $this->junipay->isConfigured()) {
            return $stats;
        }

        $withdrawals = Withdrawal::query()
            ->where('status', WithdrawalStatus::Processing)
            ->where('payout_channel', 'junipay')
            ->whereNotNull('junipay_trans_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($withdrawals as $withdrawal) {
            $stats['checked']++;
            try {
                $status = $this->junipay->transactionStatus((string) $withdrawal->junipay_trans_id);
                $this->applyJunipayStatus($withdrawal, $status, '');
                $stats[match ($status) {
                    'success' => 'paid',
                    'failed' => 'failed',
                    default => 'skipped',
                }]++;
            } catch (\Throwable $e) {
                $stats['skipped']++;
                Log::warning('Junipay transfer reconcile failed', ['withdrawal_id' => $withdrawal->id, 'error' => $e->getMessage()]);
            }
        }

        return $stats;
    }

    /**
     * Send withdrawals that are still waiting. Turning auto payout on does not
     * touch requests already in the queue, and a failed send used to stay there.
     *
     * @return array{sent: int, failed: int, skipped: int}
     */
    public function dispatchAutoJunipayPayouts(int $limit = 20): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        if (! PlatformSettings::junipaySettings()['auto_payout'] || ! $this->junipay->payoutsAvailable()) {
            return $stats;
        }

        $withdrawals = Withdrawal::query()
            ->where('status', WithdrawalStatus::Pending)
            ->whereNull('junipay_trans_id')
            ->whereNull('paystack_reference')
            ->where(function ($query) {
                $query->whereNull('failure_reason')
                    ->orWhere('failure_reason', 'not like', 'Junipay%');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($withdrawals as $withdrawal) {
            try {
                if ($this->attachExistingJunipayTransfer($withdrawal)) {
                    $stats['sent']++;

                    continue;
                }
                $this->processJunipay($withdrawal, null);
                $stats['sent']++;
            } catch (\Throwable $e) {
                $stats['failed']++;
                $withdrawal->update([
                    'failure_reason' => 'Junipay auto payout failed: '.$e->getMessage(),
                ]);
                Log::warning('Junipay auto payout failed', [
                    'withdrawal_id' => $withdrawal->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * A previous send may have reached Junipay without us storing its id.
     * Junipay finds that transfer when we ask with our own reference.
     */
    private function attachExistingJunipayTransfer(Withdrawal $withdrawal): bool
    {
        if (! filled($withdrawal->junipay_reference)) {
            return false;
        }

        try {
            $status = $this->junipay->transactionStatus((string) $withdrawal->junipay_reference);
        } catch (\Throwable) {
            return false;
        }

        $withdrawal->update([
            'status' => WithdrawalStatus::Processing,
            'junipay_trans_id' => $withdrawal->junipay_reference,
            'junipay_status' => $status,
            'payout_channel' => 'junipay',
            'failure_reason' => null,
        ]);
        $this->applyJunipayStatus($withdrawal->fresh() ?? $withdrawal, $status, '');

        return true;
    }

    private function applyJunipayStatus(Withdrawal $withdrawal, string $status, string $message): void
    {
        if ($status === 'success') {
            $withdrawal->update(['junipay_status' => 'success']);
            $this->markAsPaid($withdrawal->fresh() ?? $withdrawal, 'junipay');
        } elseif ($status === 'failed') {
            $withdrawal->update([
                'status' => WithdrawalStatus::Pending,
                'junipay_status' => 'failed',
                'payout_channel' => 'junipay',
                'failure_reason' => $message !== '' ? 'Junipay: '.$message : 'Junipay payout did not go through.',
            ]);
        }
    }

    /**
     * Auto path — no admin user. Leaves request pending if Paystack is not ready.
     *
     * @return array{otp_required: bool, transfer_code: string|null, message: string}
     */
    public function processAuto(Withdrawal $withdrawal): array
    {
        return $this->process($withdrawal, null);
    }

    /**
     * @return array{otp_required: bool, transfer_code: string|null, message: string}
     */
    public function process(Withdrawal $withdrawal, ?User $admin): array
    {
        if (! in_array($withdrawal->status, [WithdrawalStatus::Pending, WithdrawalStatus::Processing], true)) {
            throw new \RuntimeException('Only pending or processing withdrawals can be sent via Paystack.');
        }

        if ($withdrawal->status === WithdrawalStatus::Processing && filled($withdrawal->paystack_reference)) {
            throw new \RuntimeException('This withdrawal was already sent to Paystack. Wait for confirmation or run reconcile.');
        }

        if (! $this->paystack->isWithdrawalOffered()) {
            throw new \RuntimeException($this->paystack->unavailableMessage('withdrawal'));
        }

        return DB::transaction(function () use ($withdrawal, $admin) {
            $withdrawal = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! in_array($withdrawal->status, [WithdrawalStatus::Pending, WithdrawalStatus::Processing], true)) {
                throw new \RuntimeException('This withdrawal can no longer be paid out.');
            }

            if ($withdrawal->status === WithdrawalStatus::Processing && filled($withdrawal->paystack_reference)) {
                throw new \RuntimeException('This withdrawal was already sent to Paystack.');
            }

            $recipientCode = $this->resolveRecipientCode($withdrawal);
            $reference = PaymentReference::withdrawal((int) $withdrawal->id);

            $transfer = $this->paystack->initiateTransfer(
                $recipientCode,
                (float) $withdrawal->amount,
                $reference,
                'CityShop withdrawal #'.$withdrawal->id,
            );

            $transferStatus = (string) ($transfer['status'] ?? 'pending');

            $withdrawal->update([
                'status' => WithdrawalStatus::Processing,
                'paystack_recipient_code' => $recipientCode,
                'paystack_reference' => $reference,
                'paystack_transfer_code' => $transfer['transfer_code'] ?? null,
                'paystack_status' => $transferStatus,
                'payout_channel' => 'paystack',
                'failure_reason' => null,
                'processed_by' => $admin?->id ?? $withdrawal->processed_by,
            ]);

            if ($transferStatus === 'success') {
                $this->markAsPaid($withdrawal->fresh(), 'paystack');

                return [
                    'otp_required' => false,
                    'transfer_code' => $transfer['transfer_code'] ?? null,
                    'message' => 'Payout sent successfully via Paystack.',
                ];
            }

            if ($transferStatus === 'otp') {
                return [
                    'otp_required' => true,
                    'transfer_code' => $transfer['transfer_code'] ?? null,
                    'message' => $admin
                        ? 'Paystack OTP required. Enter the code sent to your business phone to complete this payout.'
                        : 'Payout started. Waiting for Paystack confirmation.',
                ];
            }

            return [
                'otp_required' => false,
                'transfer_code' => $transfer['transfer_code'] ?? null,
                'message' => 'Payout initiated. Status will update when Paystack confirms the transfer.',
            ];
        });
    }

    public function finalizeWithOtp(Withdrawal $withdrawal, string $otp, User $admin): void
    {
        if ($withdrawal->status !== WithdrawalStatus::Processing) {
            throw new \RuntimeException('This withdrawal is not awaiting OTP confirmation.');
        }

        if (! $withdrawal->paystack_transfer_code) {
            throw new \RuntimeException('Missing Paystack transfer code.');
        }

        $transfer = $this->paystack->finalizeTransfer($withdrawal->paystack_transfer_code, $otp);

        $transferStatus = (string) ($transfer['status'] ?? 'pending');

        $withdrawal->update([
            'paystack_status' => $transferStatus,
            'processed_by' => $admin->id,
        ]);

        if ($transferStatus === 'success') {
            $this->markAsPaid($withdrawal->fresh(), 'paystack');

            return;
        }

        if ($transferStatus === 'failed') {
            $this->markAsFailed($withdrawal->fresh(), 'Paystack transfer failed after OTP.');
        }
    }

    public function markAsPaid(Withdrawal $withdrawal, string $payoutChannel = 'paystack', ?string $proofPath = null, ?string $adminNotes = null): void
    {
        if ($withdrawal->status === WithdrawalStatus::Paid) {
            return;
        }

        $withdrawal->update([
            'status' => WithdrawalStatus::Paid,
            'processed_at' => now(),
            'paystack_status' => $payoutChannel === 'paystack' ? 'success' : $withdrawal->paystack_status,
            'payout_channel' => $payoutChannel,
            'proof_path' => $proofPath ?? $withdrawal->proof_path,
            'admin_notes' => $adminNotes ?? $withdrawal->admin_notes,
        ]);

        $withdrawal->user->wallet?->increment('withdrawn_amount', $withdrawal->amount);

        // Keep a single wallet receipt (the original debit). Status becomes Completed
        // on that same row via WalletTransactionService::displayTypeLabel().

        $this->notifyDebit($withdrawal->fresh() ?? $withdrawal);
    }

    /**
     * The wallet-debit text. Auto payouts send it when the request is created.
     * Manual payouts send it only when the withdrawal is confirmed, and never twice.
     */
    public function notifyDebit(Withdrawal $withdrawal): void
    {
        if ($withdrawal->debit_notified_at !== null) {
            return;
        }

        try {
            $withdrawal->loadMissing('user.wallet');
            $user = $withdrawal->user;
            if (! $user) {
                return;
            }

            $user->notify(new WithdrawalRequestedNotification(
                $withdrawal,
                (float) ($user->wallet?->available_balance ?? 0),
            ));
            $withdrawal->update(['debit_notified_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function startProcessing(Withdrawal $withdrawal, User $admin): void
    {
        if ($withdrawal->status !== WithdrawalStatus::Pending) {
            throw new \RuntimeException('Only pending withdrawals can be started.');
        }

        $withdrawal->update([
            'status' => WithdrawalStatus::Processing,
            'processed_by' => $admin->id,
            'payout_channel' => $withdrawal->payout_channel ?? 'manual',
        ]);
    }

    public function markAsFailed(Withdrawal $withdrawal, string $reason): void
    {
        if (in_array($withdrawal->status, [WithdrawalStatus::Paid, WithdrawalStatus::Rejected], true)) {
            return;
        }

        $withdrawal->update([
            'status' => WithdrawalStatus::Rejected,
            'rejection_reason' => $reason,
            'failure_reason' => $reason,
            'processed_at' => now(),
            'paystack_status' => 'failed',
        ]);

        $withdrawal->user->wallet?->increment('available_balance', $withdrawal->totalDebited());

        WalletTransactionService::recordWithdrawalRefunded($withdrawal);

        try {
            $withdrawal->loadMissing('user');
            $withdrawal->user?->notify(new WithdrawalRejectedNotification($withdrawal->fresh() ?? $withdrawal));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleTransferWebhook(array $data, ?string $event = null): void
    {
        $reference = $data['reference'] ?? null;
        $transferCode = $data['transfer_code'] ?? null;

        $withdrawal = null;
        if ($reference) {
            $withdrawal = Withdrawal::where('paystack_reference', $reference)->first();
        }
        if (! $withdrawal && $transferCode) {
            $withdrawal = Withdrawal::where('paystack_transfer_code', $transferCode)->first();
        }

        if (! $withdrawal) {
            Log::warning('Paystack transfer webhook: withdrawal not found', [
                'event' => $event,
                'reference' => $reference,
                'transfer_code' => $transferCode,
            ]);

            return;
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        $eventName = strtolower((string) ($event ?? ''));

        if ($status === 'success' || $eventName === 'transfer.success') {
            $this->markAsPaid($withdrawal, 'paystack');

            return;
        }

        if (in_array($status, ['failed', 'reversed'], true)
            || in_array($eventName, ['transfer.failed', 'transfer.reversed'], true)) {
            $reason = (string) ($data['complete_message'] ?? $data['reason'] ?? 'Paystack transfer failed.');
            $this->markAsFailed($withdrawal, $reason);
        }
    }

    /**
     * Reconcile Paystack withdrawals stuck in processing by verifying transfer status.
     *
     * @return array{checked: int, paid: int, failed: int, skipped: int}
     */
    public function reconcilePendingPaystackTransfers(int $limit = 50): array
    {
        $stats = ['checked' => 0, 'paid' => 0, 'failed' => 0, 'skipped' => 0];

        $withdrawals = Withdrawal::query()
            ->where('status', WithdrawalStatus::Processing)
            ->where('payout_channel', 'paystack')
            ->whereNotNull('paystack_reference')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($withdrawals as $withdrawal) {
            $stats['checked']++;

            try {
                $data = $this->paystack->verifyTransfer((string) $withdrawal->paystack_reference);
                $status = strtolower((string) ($data['status'] ?? ''));

                $withdrawal->update([
                    'paystack_status' => $status !== '' ? $status : $withdrawal->paystack_status,
                    'paystack_transfer_code' => $data['transfer_code'] ?? $withdrawal->paystack_transfer_code,
                ]);

                if ($status === 'success') {
                    $this->markAsPaid($withdrawal->fresh() ?? $withdrawal, 'paystack');
                    $stats['paid']++;
                } elseif (in_array($status, ['failed', 'reversed', 'abandoned'], true)) {
                    $reason = (string) ($data['complete_message'] ?? $data['reason'] ?? 'Paystack transfer failed.');
                    $this->markAsFailed($withdrawal->fresh() ?? $withdrawal, $reason);
                    $stats['failed']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $stats['skipped']++;
                Log::warning('Paystack transfer reconcile failed', [
                    'withdrawal_id' => $withdrawal->id,
                    'reference' => $withdrawal->paystack_reference,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function resolveRecipientCode(Withdrawal $withdrawal): string
    {
        if ($withdrawal->paystack_recipient_code) {
            return $withdrawal->paystack_recipient_code;
        }

        if ($withdrawal->payout_method_id) {
            $payoutMethod = SellerPayoutMethod::find($withdrawal->payout_method_id);

            if ($payoutMethod?->paystack_recipient_code) {
                $withdrawal->update(['paystack_recipient_code' => $payoutMethod->paystack_recipient_code]);

                return $payoutMethod->paystack_recipient_code;
            }
        }

        $isBank = ($withdrawal->payout_channel === 'bank') || GhanaBanks::isBank($withdrawal->network);
        $withdrawal->loadMissing('user');
        $recipientName = trim((string) ($withdrawal->account_name ?: $withdrawal->user?->name ?: 'CityShop payout'));

        $recipient = $isBank
            ? $this->paystack->createBankRecipient(
                $recipientName,
                $withdrawal->momo_number,
                (string) $withdrawal->network,
            )
            : $this->paystack->createMobileMoneyRecipient(
                $recipientName,
                $withdrawal->momo_number,
                (string) $withdrawal->network,
            );

        $recipientCode = (string) ($recipient['recipient_code'] ?? '');
        if ($recipientCode === '') {
            throw new \RuntimeException('Paystack did not return a recipient code for this MoMo/bank account.');
        }

        $withdrawal->update(['paystack_recipient_code' => $recipientCode]);

        if ($withdrawal->payout_method_id) {
            SellerPayoutMethod::where('id', $withdrawal->payout_method_id)
                ->whereNull('paystack_recipient_code')
                ->update(['paystack_recipient_code' => $recipientCode]);
        }

        return $recipientCode;
    }
}
