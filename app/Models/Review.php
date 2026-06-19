<?php

namespace App\Models;

use App\Services\ReviewService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'comment',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected static function booted(): void
    {
        static::deleted(function (Review $review) {
            $product = $review->product;

            if ($product) {
                ReviewService::syncProductRating($product);
                ReviewService::syncSellerRating($product->seller_id);
            }
        });
    }
}
