<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellRmbAdminNote extends Model
{
    protected $fillable = [
        'sell_rmb_transfer_id',
        'admin_id',
        'note',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(SellRmbTransfer::class, 'sell_rmb_transfer_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
