<?php

namespace App\Models;

use App\Enums\ChinaTransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ChinaTransfer extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'status',
        'ghs_amount',
        'rmb_amount',
        'fee_ghs',
        'total_payable_ghs',
        'ghs_per_rmb',
        'fee_mode',
        'fee_value',
        'rate_id',
        'payment_method_id',
        'payment_reference',
        'payment_proof_path',
        'needs_approval',
        'assigned_admin_id',
        'paid_at',
        'verified_at',
        'processing_at',
        'sent_at',
        'completed_at',
        'cancelled_at',
        'rejection_reason',
        'rmb_sent_amount',
        'rmb_transfer_ref',
        'rmb_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ChinaTransferStatus::class,
            'ghs_amount' => 'decimal:2',
            'rmb_amount' => 'decimal:2',
            'fee_ghs' => 'decimal:2',
            'total_payable_ghs' => 'decimal:2',
            'ghs_per_rmb' => 'decimal:4',
            'fee_value' => 'decimal:2',
            'needs_approval' => 'boolean',
            'paid_at' => 'datetime',
            'verified_at' => 'datetime',
            'processing_at' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rmb_sent_amount' => 'decimal:2',
            'rmb_sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rate(): BelongsTo
    {
        return $this->belongsTo(ChinaTransferRate::class, 'rate_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(ChinaTransferPaymentMethod::class, 'payment_method_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ChinaTransferFieldValue::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(ChinaTransferProof::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ChinaTransferStatusHistory::class)->latest();
    }

    public function adminNotes(): HasMany
    {
        return $this->hasMany(ChinaTransferAdminNote::class)->latest();
    }

    public function paymentProofUrl(): ?string
    {
        return $this->payment_proof_path
            ? Storage::disk('public')->url($this->payment_proof_path)
            : null;
    }
}
