<?php

namespace App\Models;

use App\Enums\FundsReleaseStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
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
        'funds_release_status',
        'funds_release_notes',
        'funds_reviewed_by',
        'funds_released_at',
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
            'funds_release_status' => FundsReleaseStatus::class,
            'cancelled_at' => 'datetime',
            'funds_released_at' => 'datetime',
        ];
    }

    public function fundsReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'funds_reviewed_by');
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

    /**
     * Hide unpaid “Pay to seller” orders until the buyer submits a payment claim.
     * Marketplace, COD, paid direct, and claimed direct orders stay visible.
     */
    public function scopeVisibleToSeller(Builder $query): Builder
    {
        return $query->whereHas('order', function (Builder $order) {
            $order->where(function (Builder $q) {
                $q->where('payment_channel', '!=', PaymentChannel::Direct)
                    ->orWhere('payment_status', '!=', PaymentStatus::Pending)
                    ->orWhereNotNull('direct_payment_reference')
                    ->orWhereNotNull('direct_payment_proof_path');
            });
        });
    }

    public function lineTotal(): float
    {
        return $this->quantity * (float) $this->unit_price;
    }
}
