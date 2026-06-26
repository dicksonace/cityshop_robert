<?php

namespace App\Models;

use App\Enums\SellerPaymentMethodType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerPaymentMethod extends Model
{
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
    ];

    protected function casts(): array
    {
        return [
            'type' => SellerPaymentMethodType::class,
            'metadata' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
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
