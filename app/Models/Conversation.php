<?php

namespace App\Models;

use App\Services\ChatService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = [
        'buyer_id',
        'seller_id',
        'product_id',
        'last_message_at',
        'buyer_hidden_at',
        'seller_hidden_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'buyer_hidden_at' => 'datetime',
            'seller_hidden_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    /** The newest message a participant actually sees, ignoring call signalling. */
    public function latestVisibleMessage(): HasOne
    {
        return $this->hasOne(Message::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->whereIn('type', ChatService::visibleTypes()),
        );
    }

    public function otherParticipant(User $user): User
    {
        return $this->buyer_id === $user->id ? $this->seller : $this->buyer;
    }

    public function involves(User $user): bool
    {
        return $this->buyer_id === $user->id || $this->seller_id === $user->id;
    }

    public function isHiddenFor(User $user): bool
    {
        if ($this->buyer_id === $user->id) {
            return $this->buyer_hidden_at !== null;
        }
        if ($this->seller_id === $user->id) {
            return $this->seller_hidden_at !== null;
        }

        return false;
    }

    public function hideFor(User $user): void
    {
        if ($this->buyer_id === $user->id) {
            $this->forceFill(['buyer_hidden_at' => now()])->save();
        } elseif ($this->seller_id === $user->id) {
            $this->forceFill(['seller_hidden_at' => now()])->save();
        }
    }

    public function clearHiddenFor(User $user): void
    {
        if ($this->buyer_id === $user->id && $this->buyer_hidden_at) {
            $this->forceFill(['buyer_hidden_at' => null])->save();
        } elseif ($this->seller_id === $user->id && $this->seller_hidden_at) {
            $this->forceFill(['seller_hidden_at' => null])->save();
        }
    }

    public function clearHiddenForAll(): void
    {
        if ($this->buyer_hidden_at || $this->seller_hidden_at) {
            $this->forceFill([
                'buyer_hidden_at' => null,
                'seller_hidden_at' => null,
            ])->save();
        }
    }
}
