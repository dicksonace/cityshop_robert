<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminBuyerAnnouncement extends Model
{
    protected $fillable = [
        'admin_id',
        'audience',
        'title',
        'body',
        'buyer_user_ids',
        'send_email',
        'recipients_count',
    ];

    protected function casts(): array
    {
        return [
            'buyer_user_ids' => 'array',
            'send_email' => 'boolean',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
