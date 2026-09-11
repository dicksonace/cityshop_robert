<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GsmServiceField extends Model
{
    public const TYPES = ['text', 'textarea', 'number', 'url', 'phone'];

    protected $fillable = [
        'gsm_service_id',
        'name',
        'label',
        'placeholder',
        'type',
        'required',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(GsmService::class, 'gsm_service_id');
    }
}
