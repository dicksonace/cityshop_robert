<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    protected $fillable = [
        'user_id',
        'available_balance',
        'pending_balance',
        'total_earnings',
        'withdrawn_amount',
    ];

    protected function casts(): array
    {
        return [
            'available_balance' => 'decimal:2',
            'pending_balance' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'withdrawn_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Inertia/JSON payload with numeric balances (decimal casts otherwise serialize as strings).
     *
     * @return array{available_balance: float, pending_balance: float, total_earnings: float, withdrawn_amount: float}
     */
    public function toFrontendArray(): array
    {
        return [
            'available_balance' => (float) $this->available_balance,
            'pending_balance' => (float) $this->pending_balance,
            'total_earnings' => (float) $this->total_earnings,
            'withdrawn_amount' => (float) $this->withdrawn_amount,
        ];
    }

    /**
     * @return array{available_balance: float, pending_balance: float, total_earnings: float, withdrawn_amount: float}
     */
    public static function emptyFrontendArray(): array
    {
        return [
            'available_balance' => 0.0,
            'pending_balance' => 0.0,
            'total_earnings' => 0.0,
            'withdrawn_amount' => 0.0,
        ];
    }
}
