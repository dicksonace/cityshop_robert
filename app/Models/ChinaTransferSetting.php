<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChinaTransferSetting extends Model
{
    protected $fillable = [
        'enabled',
        'channel',
        'instructions',
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
            'channel' => 'alipay',
            'instructions' => null,
        ]);
    }
}
