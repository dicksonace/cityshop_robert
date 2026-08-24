<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class UserStatus extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'body',
        'media_path',
        'background_color',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(StatusView::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }

    public function mediaUrl(): ?string
    {
        if (! is_string($this->media_path) || trim($this->media_path) === '') {
            return null;
        }

        return Storage::disk('public')->url(ltrim($this->media_path, '/'));
    }
}
