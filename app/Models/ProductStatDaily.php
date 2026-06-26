<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductStatDaily extends Model
{
    protected $table = 'product_stat_daily';

    protected $fillable = [
        'product_id',
        'date',
        'views',
        'cart_adds',
        'purchases',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
