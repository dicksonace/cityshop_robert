<?php

namespace App\Services;

use App\Enums\WithdrawalStatus;
use App\Models\SellerPayoutMethod;
use App\Models\User;
use App\Models\Withdrawal;
use App\Notifications\WithdrawalPaidNotification;
use App\Notifications\WithdrawalRejectedNotification;
use App\Support\GhanaBanks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalPayoutService
{
    public function __construct(private PaystackService $paystack) {}

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
        if ($withdrawal->status !== WithdrawalStatus::Pending) {
            throw new \RuntimeException('Only pending withdrawals can be processed.');
        }

        if (! $this->paystack->isConfigured()) {
            throw new \RuntimeException('Paystack is not configured. Add PAYSTACK keys to enable payouts.');
        }

        return DB::transaction(function () use ($withdrawal, $admin) {
            $withdrawal = Withdrawal::whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($withdrawal->status !== WithdrawalStatus::Pending) {
                throw new \RuntimeException('This withdrawal is no longer pending.');
            }

            $recipientCode = $this->resolveRecipientCode($withdrawal);
            $reference = 'WD-'.$withdrawal->id.'-'.strtoupper(substr(uniqid(), -10));

            $transfer = $this->paystack->initiateTransfer(
                $recipientCode,
                (float) $withdrawal->amount,
                $reference,
                'CityShop wallet withdrawal #'.$withdrawal->id,
            );

            $transferStatus = (string) ($transfer['status'] ?? 'pending');

            $withdrawal->update([
                'status' => WithdrawalStatus::Processing,
                'paystack_recipient_code' => $recipientCode,
                'paystack_reference' => $reference,
                'paystack_transfer_code' => $transfer['transfer_code'] ?? null,
                'paystack_status' => $transferStatus,
                'payout_channel' => 'paystack',
                'processed_by' => $admin?->id,
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

        try {
            $withdrawal->loadMissing('user');
            $withdrawal->user?->notify(new WithdrawalPaidNotification($withdrawal));
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

        $recipient = $isBank
            ? $this->paystack->createBankRecipient(
                $withdrawal->account_name,
                $withdrawal->momo_number,
                (string) $withdrawal->network,
            )
            : $this->paystack->createMobileMoneyRecipient(
                $withdrawal->account_name,
                $withdrawal->momo_number,
                $withdrawal->network,
            );

        $recipientCode = (string) $recipient['recipient_code'];

        $withdrawal->update(['paystack_recipient_code' => $recipientCode]);

        if ($withdrawal->payout_method_id) {
            SellerPayoutMethod::where('id', $withdrawal->payout_method_id)
                ->whereNull('paystack_recipient_code')
                ->update(['paystack_recipient_code' => $recipientCode]);
        }

        return $recipientCode;
    }
}
