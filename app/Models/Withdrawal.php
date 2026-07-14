<?php

namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'user_id',
        'payout_method_id',
        'amount',
        'momo_number',
        'account_name',
        'network',
        'paystack_recipient_code',
        'paystack_transfer_code',
        'paystack_reference',
        'paystack_status',
        'payout_channel',
        'status',
        'rejection_reason',
        'failure_reason',
        'proof_path',
        'admin_notes',
        'processed_by',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => WithdrawalStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function payoutMethod(): BelongsTo
    {
        return $this->belongsTo(SellerPayoutMethod::class, 'payout_method_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
