<?php

namespace App\Models;

use App\Enums\GsmOrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GsmOrder extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'gsm_service_id',
        'service_name',
        'price_ghs',
        'status',
        'paid_at',
        'processing_at',
        'completed_at',
        'cancelled_at',
        'failed_at',
        'refunded',
        'admin_result_note',
        'failure_reason',
        'assigned_admin_id',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'price_ghs' => 'decimal:2',
            'status' => GsmOrderStatus::class,
            'paid_at' => 'datetime',
            'processing_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GsmService::class, 'gsm_service_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(GsmOrderFieldValue::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(GsmOrderStatusHistory::class)->latest('id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }
}
