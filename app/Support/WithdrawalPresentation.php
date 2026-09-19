<?php

namespace App\Support;

use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Storage;

class WithdrawalPresentation
{
    /**
     * Buyer/seller status card — same shape as Sell RMB.
     *
     * @return array<string, mixed>
     */
    public static function forUser(Withdrawal $withdrawal): array
    {
        $status = $withdrawal->status instanceof WithdrawalStatus
            ? $withdrawal->status
            : WithdrawalStatus::Pending;
        $isBank = $withdrawal->payout_channel === 'bank' || GhanaBanks::isBank($withdrawal->network);
        $networkLabel = PayoutNetwork::label($withdrawal->network);

        return [
            'id' => $withdrawal->id,
            'reference' => PaymentReference::withdrawalLedger((int) $withdrawal->id),
            'status' => $status->value,
            'status_label' => $status->buyerLabel(),
            'status_presentation' => $status->buyerPresentation(),
            'processing' => $status->isOpen(),
            'amount' => (float) $withdrawal->amount,
            'fee' => (float) ($withdrawal->fee ?? 0),
            'total_debited' => $withdrawal->totalDebited(),
            'you_receive' => (float) $withdrawal->amount,
            'payout_type' => $isBank ? 'bank' : 'momo',
            'payout_channel' => $withdrawal->payout_channel,
            'payout_account' => [
                'network' => $networkLabel,
                'number' => $withdrawal->momo_number,
                'account_name' => $withdrawal->account_name,
                'kind' => $isBank ? 'Bank' : 'MoMo',
            ],
            'rejection_reason' => $withdrawal->rejection_reason,
            'failure_reason' => $withdrawal->failure_reason,
            'admin_notes' => $withdrawal->admin_notes,
            'proof_url' => $withdrawal->proof_path
                ? Storage::disk('public')->url($withdrawal->proof_path)
                : null,
            'created_at' => $withdrawal->created_at?->toIso8601String(),
            'processed_at' => $withdrawal->processed_at?->toIso8601String(),
        ];
    }
}
