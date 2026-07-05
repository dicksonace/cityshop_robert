<?php

namespace App\Services;

use App\Enums\WithdrawalStatus;
use App\Models\SellerPayoutMethod;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

class WithdrawalPayoutService
{
    public function __construct(private PaystackService $paystack) {}

    /**
     * @return array{otp_required: bool, transfer_code: string|null, message: string}
     */
    public function process(Withdrawal $withdrawal, User $admin): array
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
                'processed_by' => $admin->id,
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
                    'message' => 'Paystack OTP required. Enter the code sent to your business phone to complete this payout.',
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

    public function markAsPaid(Withdrawal $withdrawal, string $payoutChannel = 'paystack'): void
    {
        if ($withdrawal->status === WithdrawalStatus::Paid) {
            return;
        }

        $withdrawal->update([
            'status' => WithdrawalStatus::Paid,
            'processed_at' => now(),
            'paystack_status' => $payoutChannel === 'paystack' ? 'success' : $withdrawal->paystack_status,
            'payout_channel' => $payoutChannel,
        ]);

        $withdrawal->user->wallet?->increment('withdrawn_amount', $withdrawal->amount);

        WalletTransactionService::recordWithdrawalCompleted($withdrawal);
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

        $withdrawal->user->wallet?->increment('available_balance', $withdrawal->amount);

        WalletTransactionService::recordWithdrawalRefunded($withdrawal);
    }

    public function handleTransferWebhook(array $data): void
    {
        $reference = $data['reference'] ?? null;

        if (! $reference) {
            return;
        }

        $withdrawal = Withdrawal::where('paystack_reference', $reference)->first();

        if (! $withdrawal) {
            return;
        }

        $status = (string) ($data['status'] ?? '');

        if ($status === 'success') {
            $this->markAsPaid($withdrawal, 'paystack');

            return;
        }

        if ($status === 'failed') {
            $reason = (string) ($data['complete_message'] ?? $data['reason'] ?? 'Paystack transfer failed.');
            $this->markAsFailed($withdrawal, $reason);
        }
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

        $recipient = $this->paystack->createMobileMoneyRecipient(
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
