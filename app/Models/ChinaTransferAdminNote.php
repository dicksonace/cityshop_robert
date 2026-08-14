<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChinaTransferAdminNote extends Model
{
    protected $fillable = [
        'china_transfer_id',
        'admin_id',
        'note',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(ChinaTransfer::class, 'china_transfer_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
