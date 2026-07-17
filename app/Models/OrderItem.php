<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'seller_id',
        'product_name',
        'quantity',
        'unit_price',
        'commission_rate',
        'commission_amount',
        'seller_amount',
        'status',
        'rejection_reason',
        'cancellation_code',
        'cancelled_by',
        'cancelled_at',
        'refund_status',
        'courier_name',
        'tracking_number',
        'vehicle_number',
        'driver_phone',
        'package_image',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'seller_amount' => 'decimal:2',
            'status' => OrderStatus::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }

    public function lineTotal(): float
    {
        return $this->quantity * (float) $this->unit_price;
    }
}
