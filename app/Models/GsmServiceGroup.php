<?php

namespace App\Models;

use App\Enums\GsmServiceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class GsmServiceGroup extends Model
{
    protected $fillable = [
        'service_type',
        'name',
        'slug',
        'image',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'service_type' => GsmServiceType::class,
            'active' => 'boolean',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(GsmService::class)->orderBy('sort_order')->orderBy('id');
    }

    public function imageUrl(): ?string
    {
        if (! filled($this->image)) {
            return null;
        }

        return url(Storage::disk('public')->url($this->image));
    }
}
