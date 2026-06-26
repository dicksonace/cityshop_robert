<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerCouponUsage extends Model
{
    protected $fillable = [
        'seller_coupon_id',
        'user_id',
        'order_id',
    ];

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(SellerCoupon::class, 'seller_coupon_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
