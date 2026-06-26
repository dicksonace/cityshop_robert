<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'path',
        'is_primary',
        'sort_order',
        'color_signature',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'color_signature' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
