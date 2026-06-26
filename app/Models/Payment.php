<?php

namespace App\Models;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'checkout_id',
        'order_id',
        'seller_id',
        'channel',
        'method',
        'amount',
        'status',
        'reference',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'channel' => PaymentChannel::class,
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
