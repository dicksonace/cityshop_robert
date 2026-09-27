<?php

namespace App\Models;

use App\Enums\ContentReportTarget;
use App\Enums\SellerReportReason;
use App\Enums\SellerReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentReport extends Model
{
    protected $fillable = [
        'reporter_id',
        'target_type',
        'target_id',
        'owner_id',
        'reason',
        'details',
        'status',
        'admin_notes',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'target_type' => ContentReportTarget::class,
            'reason' => SellerReportReason::class,
            'status' => SellerReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
