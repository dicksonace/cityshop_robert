<?php

namespace App\Models;

use App\Enums\GsmServiceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class GsmService extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'service_type',
        'gsm_service_group_id',
        'description',
        'image',
        'overview',
        'features',
        'what_to_send',
        'eta_label',
        'allow_quantity',
        'min_qty',
        'max_qty',
        'price_ghs',
        'currency',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'service_type' => GsmServiceType::class,
            'price_ghs' => 'decimal:2',
            'active' => 'boolean',
            'allow_quantity' => 'boolean',
            'features' => 'array',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(GsmServiceGroup::class, 'gsm_service_group_id');
    }

    public function imageUrl(): ?string
    {
        if (filled($this->image)) {
            return url(Storage::disk('public')->url($this->image));
        }

        return $this->group?->imageUrl() ?: url('/images/gsm/gmt-logo.jpg');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(GsmServiceField::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeFields(): HasMany
    {
        return $this->fields()->where('active', true);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(GsmOrder::class);
    }
}
