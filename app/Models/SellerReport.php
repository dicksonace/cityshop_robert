<?php

namespace App\Models;

use App\Enums\SellerReportReason;
use App\Enums\SellerReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerReport extends Model
{
    protected $fillable = [
        'reporter_id',
        'seller_id',
        'product_id',
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
            'reason' => SellerReportReason::class,
            'status' => SellerReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [SellerReportStatus::Open, SellerReportStatus::Reviewing], true);
    }
}
