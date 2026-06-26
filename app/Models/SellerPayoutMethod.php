<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SellerPayoutMethod extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'network',
        'account_number',
        'account_name',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'payout_method_id');
    }

    public function networkLabel(): string
    {
        return match ($this->network) {
            'mtn' => 'MTN Mobile Money',
            'telecel' => 'Telecel Cash',
            'airteltigo' => 'AirtelTigo Money',
            default => ucfirst($this->network),
        };
    }
}
