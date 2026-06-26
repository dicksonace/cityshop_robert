<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreCustomization extends Model
{
    protected $fillable = [
        'seller_profile_id',
        'published_settings',
        'draft_settings',
        'setup_completed_at',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_settings' => 'array',
            'draft_settings' => 'array',
            'setup_completed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function isSetupComplete(): bool
    {
        return $this->setup_completed_at !== null;
    }
}
