<?php

namespace App\Models;

use App\Enums\SellerPaymentMethodType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SellerPaymentMethod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'seller_profile_id',
        'type',
        'label',
        'account_name',
        'account_number',
        'network',
        'bank_name',
        'instructions',
        'metadata',
        'is_active',
        'is_default',
        'disabled_at',
        'disabled_by',
        'disabled_reason',
    ];

    protected function casts(): array
    {
        return [
            'type' => SellerPaymentMethodType::class,
            'metadata' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by');
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function displayLabel(): string
    {
        if ($this->type === SellerPaymentMethodType::MobileMoney) {
            return ($this->network ?? 'Mobile Money').' — '.$this->account_number;
        }

        if ($this->type === SellerPaymentMethodType::Bank) {
            return ($this->bank_name ?? 'Bank').' — '.$this->account_number;
        }

        return $this->label ?? ucfirst(str_replace('_', ' ', $this->type->value));
    }
}
