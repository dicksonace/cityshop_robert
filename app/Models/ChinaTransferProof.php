<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChinaTransferProof extends Model
{
    protected $fillable = [
        'china_transfer_id',
        'type',
        'path',
        'original_name',
        'mime',
        'note',
        'uploaded_by',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(ChinaTransfer::class, 'china_transfer_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
