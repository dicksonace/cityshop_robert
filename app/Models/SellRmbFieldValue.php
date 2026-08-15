<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SellRmbFieldValue extends Model
{
    protected $fillable = [
        'sell_rmb_transfer_id',
        'field_id',
        'value',
        'file_path',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(SellRmbTransfer::class, 'sell_rmb_transfer_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(SellRmbFormField::class, 'field_id');
    }

    public function fileUrl(): ?string
    {
        return $this->file_path ? Storage::disk('public')->url($this->file_path) : null;
    }
}
