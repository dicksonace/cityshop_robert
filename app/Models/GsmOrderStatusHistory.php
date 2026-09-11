<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GsmOrderStatusHistory extends Model
{
    protected $table = 'gsm_order_status_history';

    protected $fillable = [
        'gsm_order_id',
        'from_status',
        'to_status',
        'note',
        'actor_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(GsmOrder::class, 'gsm_order_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
