<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GsmService extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_ghs',
        'currency',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'price_ghs' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function fields(): HasMany
    {
        return $this->hasMany(GsmServiceField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeFields(): HasMany
    {
        return $this->fields()->where('active', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(GsmOrder::class);
    }
}
