<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SellRmbProof extends Model
{
    protected $fillable = [
        'sell_rmb_transfer_id',
        'type',
        'path',
        'original_name',
        'mime',
        'note',
        'uploaded_by',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(SellRmbTransfer::class, 'sell_rmb_transfer_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): ?string
    {
        if (! filled($this->path)) {
            return null;
        }

        return Storage::disk('public')->url($this->path);
    }
}
