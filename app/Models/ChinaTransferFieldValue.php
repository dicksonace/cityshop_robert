<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChinaTransferFieldValue extends Model
{
    protected $fillable = [
        'china_transfer_id',
        'field_id',
        'value',
        'file_path',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(ChinaTransfer::class, 'china_transfer_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(ChinaTransferFormField::class, 'field_id');
    }

    public function fileUrl(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
