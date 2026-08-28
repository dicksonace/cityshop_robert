<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChinaTransferRate extends Model
{
    protected $fillable = [
        'ghs_per_rmb',
        'fee_mode',
        'fee_value',
        'min_ghs',
        'max_ghs',
        'daily_max_ghs',
        'monthly_max_ghs',
        'max_per_day',
        'approval_above_ghs',
        'active',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'ghs_per_rmb' => 'decimal:4',
            'fee_value' => 'decimal:2',
            'min_ghs' => 'decimal:2',
            'max_ghs' => 'decimal:2',
            'daily_max_ghs' => 'decimal:2',
            'monthly_max_ghs' => 'decimal:2',
            'approval_above_ghs' => 'decimal:2',
            'active' => 'boolean',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * GHS cost of 1 RMB. Some legacy rows stored RMB-per-GHS (e.g. 0.559) in ghs_per_rmb.
     */
    public function effectiveGhsPerRmb(): float
    {
        $stored = (float) $this->ghs_per_rmb;
        if ($stored <= 0) {
            return 0.0;
        }

        if ($stored < 1) {
            return round(1 / $stored, 6);
        }

        return round($stored, 6);
    }

    /** RMB received per 1 GHS — buyer-facing; 3 decimals so math matches the rate shown. */
    public function rmbPerGhs(): float
    {
        $ghsPerRmb = $this->effectiveGhsPerRmb();

        return $ghsPerRmb > 0 ? round(1 / $ghsPerRmb, 3) : 0.0;
    }
}
