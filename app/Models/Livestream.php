<?php

namespace App\Models;

use App\Enums\LivestreamStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Livestream extends Model
{
    protected $fillable = [
        'seller_id',
        'title',
        'room_name',
        'provider',
        'status',
        'viewer_count',
        'started_at',
        'last_heartbeat_at',
        'host_joined_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LivestreamStatus::class,
            'started_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'host_joined_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function isLive(): bool
    {
        return $this->status === LivestreamStatus::Live;
    }
}
