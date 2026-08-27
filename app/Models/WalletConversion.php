<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletConversion extends Model
{
    protected $fillable = [
        'user_id',
        'direction',
        'amount_ghs',
        'amount_rmb',
        'rate',
        'reference',
        'status',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'amount_ghs' => 'decimal:2',
            'amount_rmb' => 'decimal:2',
            'rate' => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
