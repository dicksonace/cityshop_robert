<?php

namespace App\Models;

use App\Enums\SellRmbStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellRmbStatusHistory extends Model
{
    protected $table = 'sell_rmb_status_history';

    protected $fillable = [
        'sell_rmb_transfer_id',
        'from_status',
        'to_status',
        'note',
        'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => SellRmbStatus::class,
            'to_status' => SellRmbStatus::class,
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(SellRmbTransfer::class, 'sell_rmb_transfer_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
