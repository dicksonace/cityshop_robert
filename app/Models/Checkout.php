<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checkout extends Model
{
    protected $fillable = [
        'checkout_number',
        'buyer_id',
        'status',
        'payment_status',
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
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public static function generateCheckoutNumber(): string
    {
        return 'CHK'.date('Ymd').strtoupper(substr(uniqid(), -6));
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function marketplaceTotal(): float
    {
        return (float) $this->orders
            ->where('payment_channel', PaymentChannel::Marketplace)
            ->sum('total');
    }

    public function isFullyPaid(): bool
    {
        return $this->payment_status === PaymentStatus::Paid;
    }
}
