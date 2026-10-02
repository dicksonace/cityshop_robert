<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'currency',
        'description',
        'order_item_id',
        'withdrawal_id',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    /**
     * Unknown ledger types must not crash the wallet page.
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): ?WalletTransactionType {
                if ($value instanceof WalletTransactionType) {
                    return $value;
                }

                return is_string($value) && $value !== ''
                    ? WalletTransactionType::tryFrom($value)
                    : null;
            },
            set: function (mixed $value): ?string {
                if ($value instanceof WalletTransactionType) {
                    return $value->value;
                }

                return is_string($value) ? $value : null;
            },
        );
    }

    public function isRmb(): bool
    {
        return strtoupper((string) ($this->currency ?? 'GHS')) === 'RMB';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }
}
