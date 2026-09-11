<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GsmOrderFieldValue extends Model
{
    protected $fillable = [
        'gsm_order_id',
        'gsm_service_field_id',
        'field_name',
        'field_label',
        'value',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(GsmOrder::class, 'gsm_order_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(GsmServiceField::class, 'gsm_service_field_id');
    }
}
