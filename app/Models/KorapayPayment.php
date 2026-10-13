<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Korapay metadata only allows 5 short keys, so the full payment context lives here.
 */
class KorapayPayment extends Model
{
    protected $fillable = [
        'reference',
        'user_id',
        'amount',
        'meta',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }
}
