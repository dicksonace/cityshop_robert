<?php

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',
        'body',
        'metadata',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Unknown / legacy type strings must not 500 the whole inbox.
     */
    protected function type(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value instanceof MessageType) {
                    return $value;
                }

                return MessageType::tryFrom((string) $value) ?? MessageType::Text;
            },
            set: function ($value) {
                if ($value instanceof MessageType) {
                    return $value->value;
                }

                return MessageType::tryFrom((string) $value)?->value ?? (string) $value;
            },
        );
    }

    /**
     * Encrypt chat bodies at rest. Legacy plaintext rows still read correctly.
     */
    protected function body(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value === null || $value === '') {
                    return $value;
                }

                try {
                    return Crypt::decryptString($value);
                } catch (Throwable) {
                    return $value;
                }
            },
            set: function (?string $value) {
                if ($value === null || $value === '') {
                    return $value;
                }

                return Crypt::encryptString($value);
            },
        );
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id')->withTrashed();
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }
}
