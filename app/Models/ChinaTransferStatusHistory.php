<?php

namespace App\Models;

use App\Enums\ChinaTransferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChinaTransferStatusHistory extends Model
{
    protected $table = 'china_transfer_status_history';

    protected $fillable = [
        'china_transfer_id',
        'from_status',
        'to_status',
        'note',
        'actor_id',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ChinaTransferStatus::class,
            'to_status' => ChinaTransferStatus::class,
        ];
    }

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(ChinaTransfer::class, 'china_transfer_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
