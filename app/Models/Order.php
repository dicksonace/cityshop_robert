<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'checkout_id',
        'order_number',
        'buyer_id',
        'seller_id',
        'status',
        'payment_status',
        'payment_method',
        'payment_channel',
        'payment_reference',
        'seller_payment_method_id',
        'direct_payment_reference',
        'direct_payment_proof_path',
        'direct_payment_confirmed_at',
        'receiver_name',
        'receiver_phone',
        'region',
        'city',
        'digital_address',
        'delivery_notes',
        'subtotal',
        'shipping_cost',
        'commission_amount',
        'discount_amount',
        'seller_coupon_id',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'payment_channel' => PaymentChannel::class,
            'direct_payment_confirmed_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
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

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(Checkout::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function sellerPaymentMethod(): BelongsTo
    {
        return $this->belongsTo(SellerPaymentMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
