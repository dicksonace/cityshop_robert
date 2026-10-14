<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Junipay Mobile Money prompt. Junipay has no hosted checkout, so CityShop keeps the context here.
 */
class JunipayPayment extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'amount',
        'meta',
        'status',
        'redirect_url',
        'phone',
        'provider',
        'trans_id',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }
}
