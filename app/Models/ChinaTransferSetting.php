<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChinaTransferSetting extends Model
{
    protected $fillable = [
        'enabled',
        'channel',
        'instructions',
        'max_converts_per_day',
        'max_rmb_out_per_day',
        'max_rmb_out_per_month',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'max_rmb_out_per_day' => 'decimal:2',
            'max_rmb_out_per_month' => 'decimal:2',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::create([
            'enabled' => true,
            'channel' => 'alipay',
            'instructions' => null,
            'max_converts_per_day' => 30,
        ]);
    }
}
