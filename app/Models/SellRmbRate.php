<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellRmbRate extends Model
{
    protected $fillable = [
        'usd_per_rmb',
        'ghs_per_usd',
        'fee_mode',
        'fee_value',
        'min_rmb',
        'max_rmb',
        'daily_max_rmb',
        'monthly_max_rmb',
        'max_per_day',
        'approval_above_rmb',
        'active',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'usd_per_rmb' => 'decimal:6',
            'ghs_per_usd' => 'decimal:4',
            'fee_value' => 'decimal:2',
            'min_rmb' => 'decimal:2',
            'max_rmb' => 'decimal:2',
            'daily_max_rmb' => 'decimal:2',
            'monthly_max_rmb' => 'decimal:2',
            'approval_above_rmb' => 'decimal:2',
            'active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
