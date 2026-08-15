<?php

namespace App\Models;

use App\Enums\SellRmbStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class SellRmbTransfer extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'status',
        'rmb_amount',
        'usd_per_rmb',
        'ghs_per_usd',
        'fee_mode',
        'fee_value',
        'fee_usd',
        'usd_payout',
        'ghs_payout',
        'payout_currency',
        'rate_id',
        'receive_method_id',
        'payment_reference',
        'payment_proof_path',
        'needs_approval',
        'assigned_admin_id',
        'submitted_at',
        'verified_at',
        'rmb_received_at',
        'payout_processing_at',
        'paid_at',
        'completed_at',
        'cancelled_at',
        'rejection_reason',
        'payout_amount',
        'payout_ref',
        'payout_paid_at',
        'payout_channel',
    ];

    protected function casts(): array
    {
        return [
            'status' => SellRmbStatus::class,
            'rmb_amount' => 'decimal:2',
            'usd_per_rmb' => 'decimal:6',
            'ghs_per_usd' => 'decimal:4',
            'fee_value' => 'decimal:2',
            'fee_usd' => 'decimal:2',
            'usd_payout' => 'decimal:2',
            'ghs_payout' => 'decimal:2',
            'needs_approval' => 'boolean',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'rmb_received_at' => 'datetime',
            'payout_processing_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'payout_amount' => 'decimal:2',
            'payout_paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(SellRmbRate::class, 'rate_id');
    }

    public function receiveMethod(): BelongsTo
    {
        return $this->belongsTo(SellRmbReceiveMethod::class, 'receive_method_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(SellRmbFieldValue::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(SellRmbProof::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(SellRmbStatusHistory::class)->latest();
    }

    public function adminNotes(): HasMany
    {
        return $this->hasMany(SellRmbAdminNote::class)->latest();
    }

    public function paymentProofUrl(): ?string
    {
        return $this->payment_proof_path
            ? Storage::disk('public')->url($this->payment_proof_path)
            : null;
    }

    public function expectedPayoutAmount(): float
    {
        return $this->payout_currency === 'ghs'
            ? (float) $this->ghs_payout
            : (float) $this->usd_payout;
    }
}
