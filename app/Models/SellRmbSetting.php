<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellRmbSetting extends Model
{
    protected $fillable = [
        'enabled',
        'instructions',
        'receive_instructions',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'enabled' => false,
            'instructions' => null,
            'receive_instructions' => null,
        ]);
    }
}
