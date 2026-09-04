<?php

namespace App\Models;

use App\Services\ChatService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

class Conversation extends Model
{
    protected $fillable = [
        'buyer_id',
        'seller_id',
        'product_id',
        'is_group',
        'name',
        'avatar',
        'created_by',
        'last_message_at',
        'buyer_hidden_at',
        'seller_hidden_at',
        'buyer_cleared_at',
        'seller_cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'last_message_at' => 'datetime',
            'buyer_hidden_at' => 'datetime',
            'seller_hidden_at' => 'datetime',
            'buyer_cleared_at' => 'datetime',
            'seller_cleared_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id')->withTrashed();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id')->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function participantRows(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot(['hidden_at', 'messages_cleared_at'])
            ->withTimestamps();
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
        if ($this->is_group) {
            $other = $this->otherParticipants($user)->first();
            if ($other) {
                return $other;
            }

            return $user;
        }

        $other = $this->buyer_id === $user->id ? $this->seller : $this->buyer;
        if ($other) {
            return $other;
        }

        // Soft-deleted / missing peer must not 500 the web chat widget.
        $fallback = new User([
            'id' => $this->buyer_id === $user->id ? (int) $this->seller_id : (int) $this->buyer_id,
            'name' => 'Deleted account',
        ]);
        $fallback->exists = false;

        return $fallback;
    }

    /** @return Collection<int, User> */
    public function otherParticipants(User $user): Collection
    {
        if ($this->is_group) {
            $this->loadMissing('participants');

            return $this->participants->where('id', '!=', $user->id)->values();
        }

        $other = $this->otherParticipant($user);

        return collect([$other])->filter();
    }

    public function involves(User $user): bool
    {
        if ($this->is_group) {
            return $this->participantRows()->where('user_id', $user->id)->exists();
        }

        return $this->buyer_id === $user->id || $this->seller_id === $user->id;
    }

    public function isHiddenFor(User $user): bool
    {
        if ($this->is_group) {
            $row = $this->participantRows()->where('user_id', $user->id)->first();

            return $row?->hidden_at !== null;
        }

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
        if ($this->is_group) {
            $this->participantRows()->where('user_id', $user->id)->update(['hidden_at' => now()]);

            return;
        }

        if ($this->buyer_id === $user->id) {
            $this->forceFill(['buyer_hidden_at' => now()])->save();
        } elseif ($this->seller_id === $user->id) {
            $this->forceFill(['seller_hidden_at' => now()])->save();
        }
    }

    public function clearHiddenFor(User $user): void
    {
        if ($this->is_group) {
            $this->participantRows()->where('user_id', $user->id)->whereNotNull('hidden_at')->update(['hidden_at' => null]);

            return;
        }

        if ($this->buyer_id === $user->id && $this->buyer_hidden_at) {
            $this->forceFill(['buyer_hidden_at' => null])->save();
        } elseif ($this->seller_id === $user->id && $this->seller_hidden_at) {
            $this->forceFill(['seller_hidden_at' => null])->save();
        }
    }

    public function clearHiddenForAll(): void
    {
        if ($this->is_group) {
            $this->participantRows()->whereNotNull('hidden_at')->update(['hidden_at' => null]);

            return;
        }

        if ($this->buyer_hidden_at || $this->seller_hidden_at) {
            $this->forceFill([
                'buyer_hidden_at' => null,
                'seller_hidden_at' => null,
            ])->save();
        }
    }

    /**
     * Soft “clear history” watermark for this viewer. Older messages stay in DB
     * (and remain visible to the other party / admins) but are hidden for this user.
     */
    public function messagesClearedAtFor(User $user): ?\Illuminate\Support\Carbon
    {
        if ($this->is_group) {
            $row = $this->participantRows()->where('user_id', $user->id)->first();

            return $row?->messages_cleared_at;
        }

        if ($this->buyer_id === $user->id) {
            return $this->buyer_cleared_at;
        }
        if ($this->seller_id === $user->id) {
            return $this->seller_cleared_at;
        }

        return null;
    }

    public function clearMessagesFor(User $user, ?\DateTimeInterface $at = null): void
    {
        $stamp = $at ? \Illuminate\Support\Carbon::parse($at) : now();

        if ($this->is_group) {
            $this->participantRows()->where('user_id', $user->id)->update([
                'messages_cleared_at' => $stamp,
            ]);

            return;
        }

        if ($this->buyer_id === $user->id) {
            $this->forceFill(['buyer_cleared_at' => $stamp])->save();
        } elseif ($this->seller_id === $user->id) {
            $this->forceFill(['seller_cleared_at' => $stamp])->save();
        }
    }

    /** Apply the same clear watermark to both direct-chat parties. */
    public function clearMessagesForBoth(?\DateTimeInterface $at = null): void
    {
        abort_if($this->is_group, 422, 'Mutual clear is only available in 1:1 chats.');

        $stamp = $at ? \Illuminate\Support\Carbon::parse($at) : now();
        $this->forceFill([
            'buyer_cleared_at' => $stamp,
            'seller_cleared_at' => $stamp,
        ])->save();
    }
}
