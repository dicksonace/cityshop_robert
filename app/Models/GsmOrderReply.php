<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GsmOrderReply extends Model
{
    protected $fillable = [
        'gsm_order_id',
        'admin_id',
        'body',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(GsmOrder::class, 'gsm_order_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
