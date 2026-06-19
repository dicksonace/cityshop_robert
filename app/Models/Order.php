<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'buyer_id',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'receiver_name',
        'receiver_phone',
        'region',
        'city',
        'digital_address',
        'delivery_notes',
        'subtotal',
        'shipping_cost',
        'commission_amount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public static function generateOrderNumber(): string
    {
        return 'CS'.date('Ymd').strtoupper(substr(uniqid(), -6));
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
