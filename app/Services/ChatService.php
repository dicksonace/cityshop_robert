<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Events\ChatMessageSent;
use App\Events\ChatMessageUpdated;
use App\Events\UserPresenceChanged;
use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatService
{
    public const EDIT_WINDOW_MINUTES = 2;

    /**
     * WebRTC signalling rows live in the same table as real messages, so every
     * thread, preview and unread tally has to skip them.
     *
     * @return array<int, MessageType>
     */
    public static function visibleTypes(): array
    {
        return [
            MessageType::Text,
            MessageType::Image,
            MessageType::Video,
            MessageType::Voice,
            MessageType::Product,
            MessageType::CallLog,
            MessageType::System,
            MessageType::Transfer,
            MessageType::File,
        ];
    }

    public static function isOnline(?User $user): bool
    {
        if (! $user?->last_seen_at) {
            return false;
        }

        return $user->last_seen_at->greaterThan(now()->subMinutes(3));
    }

    /**
     * @return array{online: bool, online_count: int, last_seen_at: ?string}
     */
    public static function presenceFor(Conversation $conversation, User $viewer): array
    {
        if ($conversation->is_group) {
            $conversation->loadMissing('participants:id,last_seen_at');
            $onlineCount = $conversation->participants
                ->filter(fn (User $member) => (int) $member->id !== (int) $viewer->id && static::isOnline($member))
                ->count();

            return [
                'online' => $onlineCount > 0,
                'online_count' => $onlineCount,
                'last_seen_at' => null,
            ];
        }

        $other = $conversation->otherParticipant($viewer);

        return [
            'online' => static::isOnline($other),
            'online_count' => static::isOnline($other) ? 1 : 0,
            'last_seen_at' => $other?->last_seen_at?->toIso8601String(),
        ];
    }

    public static function touchPresence(User $user): void
    {
        $cacheKey = 'presence:touch:'.$user->id;
        if (Cache::has($cacheKey)) {
            return;
        }
        Cache::put($cacheKey, 1, now()->addSeconds(40));

        $wasOnline = static::isOnline($user);
        $user->update(['last_seen_at' => now()]);

        if (! $wasOnline) {
            try {
                broadcast(new UserPresenceChanged($user->fresh()))->toOthers();
            } catch (\Throwable) {
                //
            }
        }
    }

    public static function findOrCreateConversation(User $buyer, User $seller, ?Product $product = null): Conversation
    {
        if (UserBlockService::isBlockedEitherWay($buyer, $seller)) {
            abort(403, 'Messaging is blocked between these accounts.');
        }

        // One thread per pair, regardless of who started (friend chat or product chat).
        $existing = Conversation::query()
            ->where(function ($q) use ($buyer, $seller) {
                $q->where('buyer_id', $buyer->id)->where('seller_id', $seller->id);
            })
            ->orWhere(function ($q) use ($buyer, $seller) {
                $q->where('buyer_id', $seller->id)->where('seller_id', $buyer->id);
            })
            ->first();

        if ($existing) {
            $existing->clearHiddenFor($buyer);
            $existing->clearHiddenFor($seller);

            return $existing;
        }

        // Do not link product_id here — that would show the item to the seller
        // before the buyer chooses to send it. Product is attached only when shared.
        return Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'is_group' => false,
        ]);
    }

    /**
     * Create a group chat. Buyers and sellers can both create groups.
     *
     * @param  list<int>  $memberIds
     */
    public static function createGroup(User $creator, string $name, array $memberIds): Conversation
    {
        $name = trim($name);
        if ($name === '') {
            abort(422, 'Enter a group name.');
        }

        $ids = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== $creator->id)
            ->unique()
            ->values();

        if ($ids->count() < 1) {
            abort(422, 'Add at least one other member.');
        }

        if ($ids->count() > 49) {
            abort(422, 'Groups can have at most 50 members.');
        }

        $members = User::query()->whereIn('id', $ids)->get();
        if ($members->count() !== $ids->count()) {
            abort(422, 'One or more members could not be found.');
        }

        foreach ($members as $member) {
            if (UserBlockService::isBlockedEitherWay($creator, $member)) {
                abort(403, 'You cannot add '.$member->name.' because messaging is blocked.');
            }
        }

        return DB::transaction(function () use ($creator, $name, $members) {
            // Keep buyer/seller columns filled for legacy NOT NULL FKs; membership is in participants.
            $conversation = Conversation::create([
                'buyer_id' => $creator->id,
                'seller_id' => $creator->id,
                'is_group' => true,
                'name' => $name,
                'created_by' => $creator->id,
                'last_message_at' => now(),
            ]);

            $participantIds = $members->pluck('id')->push($creator->id)->unique()->all();
            foreach ($participantIds as $userId) {
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                ]);
            }

            static::sendMessage(
                $conversation,
                $creator,
                $creator->name.' created the group "'.$name.'"',
                MessageType::System,
                ['system' => 'group_created'],
            );

            return $conversation->fresh(['participants', 'latestVisibleMessage']);
        });
    }

    /**
     * Add members to an existing group.
     *
     * @param  list<int>  $memberIds
     */
    public static function addGroupMembers(Conversation $conversation, User $actor, array $memberIds): Conversation
    {
        abort_unless($conversation->is_group, 422, 'Only group chats can add members.');
        abort_unless($conversation->involves($actor), 403);

        $ids = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && $id !== $actor->id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            abort(422, 'Choose at least one person to add.');
        }

        $existingIds = $conversation->participantRows()->pluck('user_id')->all();
        $newIds = $ids->reject(fn (int $id) => in_array($id, $existingIds, true))->values();

        if ($newIds->isEmpty()) {
            abort(422, 'Those people are already in the group.');
        }

        if (count($existingIds) + $newIds->count() > 50) {
            abort(422, 'Groups can have at most 50 members.');
        }

        $members = User::query()->whereIn('id', $newIds)->get();
        if ($members->count() !== $newIds->count()) {
            abort(422, 'One or more members could not be found.');
        }

        foreach ($members as $member) {
            if (UserBlockService::isBlockedEitherWay($actor, $member)) {
                abort(403, 'You cannot add '.$member->name.' because messaging is blocked.');
            }
        }

        return DB::transaction(function () use ($conversation, $actor, $members) {
            foreach ($members as $member) {
                ConversationParticipant::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $member->id,
                ]);
            }

            $names = $members->pluck('name')->implode(', ');
            static::sendMessage(
                $conversation,
                $actor,
                $actor->name.' added '.$names,
                MessageType::System,
                ['system' => 'members_added', 'member_ids' => $members->pluck('id')->all()],
            );

            return $conversation->fresh(['participants', 'latestVisibleMessage']);
        });
    }

    /** Leave a group (remove own membership). */
    public static function leaveGroup(Conversation $conversation, User $user): void
    {
        abort_unless($conversation->is_group, 422, 'Only group chats can be left.');
        abort_unless($conversation->involves($user), 403);

        DB::transaction(function () use ($conversation, $user) {
            $remaining = $conversation->participantRows()->where('user_id', '!=', $user->id)->count();

            if ($remaining > 0) {
                static::sendMessage(
                    $conversation,
                    $user,
                    $user->name.' left the group',
                    MessageType::System,
                    ['system' => 'member_left', 'user_id' => $user->id],
                );
            }

            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $user->id)
                ->delete();
        });
    }

    /** Whether this user created the group (group admin). */
    public static function isGroupAdmin(Conversation $conversation, User $user): bool
    {
        return $conversation->is_group && (int) $conversation->created_by === (int) $user->id;
    }

    /** Remove another member (group admin only; members leave via leaveGroup). */
    public static function removeGroupMember(Conversation $conversation, User $actor, User $target): Conversation
    {
        abort_unless($conversation->is_group, 422, 'Only group chats have members to remove.');
        abort_unless($conversation->involves($actor), 403);

        if ($actor->id === $target->id) {
            static::leaveGroup($conversation, $actor);

            return $conversation->fresh(['participants', 'latestVisibleMessage']) ?? $conversation;
        }

        abort_unless(static::isGroupAdmin($conversation, $actor), 403, 'Only the group admin can remove members.');
        abort_unless($conversation->involves($target), 422, 'That person is not in this group.');
        abort_if(static::isGroupAdmin($conversation, $target), 422, 'The group admin cannot be removed.');

        return DB::transaction(function () use ($conversation, $actor, $target) {
            ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', $target->id)
                ->delete();

            static::sendMessage(
                $conversation,
                $actor,
                $actor->name.' removed '.$target->name,
                MessageType::System,
                ['system' => 'member_removed', 'user_id' => $target->id],
            );

            return $conversation->fresh(['participants', 'latestVisibleMessage']);
        });
    }

    public static function updateGroupAvatar(Conversation $conversation, User $actor, string $path): Conversation
    {
        abort_unless($conversation->is_group, 422, 'Only group chats have a group photo.');
        abort_unless($conversation->involves($actor), 403);

        $old = $conversation->avatar;
        $conversation->forceFill(['avatar' => $path])->save();

        if ($old && $old !== $path) {
            Storage::disk('public')->delete($old);
        }

        static::sendMessage(
            $conversation,
            $actor,
            $actor->name.' updated the group photo',
            MessageType::System,
            ['system' => 'avatar_updated'],
        );

        return $conversation->fresh(['participants', 'latestVisibleMessage']);
    }

    public static function clearGroupAvatar(Conversation $conversation, User $actor): Conversation
    {
        abort_unless($conversation->is_group, 422, 'Only group chats have a group photo.');
        abort_unless($conversation->involves($actor), 403);

        if ($conversation->avatar) {
            Storage::disk('public')->delete($conversation->avatar);
            $conversation->forceFill(['avatar' => null])->save();

            static::sendMessage(
                $conversation,
                $actor,
                $actor->name.' removed the group photo',
                MessageType::System,
                ['system' => 'avatar_removed'],
            );
        }

        return $conversation->fresh(['participants', 'latestVisibleMessage']);
    }

    /** Visible conversations for a user (direct + groups they belong to). */
    public static function visibleConversationsQuery(int $userId)
    {
        return Conversation::query()->where(function ($q) use ($userId) {
            $q->where(function ($direct) use ($userId) {
                $direct->where('is_group', false)
                    ->where(fn ($pair) => $pair->where('buyer_id', $userId)->orWhere('seller_id', $userId))
                    ->where(function ($visible) use ($userId) {
                        $visible->where(function ($buyer) use ($userId) {
                            $buyer->where('buyer_id', $userId)->whereNull('buyer_hidden_at');
                        })->orWhere(function ($seller) use ($userId) {
                            $seller->where('seller_id', $userId)->whereNull('seller_hidden_at');
                        });
                    });
            })->orWhere(function ($group) use ($userId) {
                $group->where('is_group', true)
                    ->whereHas('participantRows', function ($p) use ($userId) {
                        $p->where('user_id', $userId)->whereNull('hidden_at');
                    });
            });
        });
    }

    /**
     * Share a product card in the thread.
     * Without $force, skips if the most recent product card is already this item.
     */
    public static function shareProductCard(
        Conversation $conversation,
        User $sender,
        Product $product,
        bool $force = false,
    ): ?Message {
        $product->loadMissing('images');

        $payload = static::productCardPayload($product);

        if (! $force) {
            $lastProduct = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('type', MessageType::Product)
                ->orderByDesc('id')
                ->first();

            $lastProductId = $lastProduct?->metadata['product']['id'] ?? null;
            if ($lastProductId !== null && (int) $lastProductId === (int) $product->id) {
                return null;
            }
        }

        if ($conversation->product_id !== $product->id) {
            $conversation->update(['product_id' => $product->id]);
        }

        return static::sendMessage(
            $conversation,
            $sender,
            $product->name,
            MessageType::Product,
            ['product' => $payload],
        );
    }

    /** Product strip is only for items actually shared in the thread (not just opened). */
    public static function sharedProductForConversation(Conversation $conversation): ?Product
    {
        $lastShare = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('type', MessageType::Product)
            ->orderByDesc('id')
            ->first();

        if (! $lastShare) {
            return null;
        }

        $productId = $lastShare->metadata['product']['id'] ?? $conversation->product_id;
        if (! $productId) {
            return null;
        }

        return Product::query()->find($productId);
    }

    /** @return array{id: int, name: string, slug: string, price: float, image_url: ?string} */
    public static function productCardPayload(Product $product): array
    {
        $product->loadMissing('images');
        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
        $path = $image?->path;
        $imageUrl = null;
        if (is_string($path) && trim($path) !== '') {
            $imageUrl = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                ? $path
                : Storage::disk('public')->url($path);
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->effectivePrice(),
            'image_url' => $imageUrl,
        ];
    }

    public static function sendMessage(
        Conversation $conversation,
        User $sender,
        string $body,
        MessageType $type = MessageType::Text,
        ?array $metadata = null,
        ?Message $replyTo = null,
    ): Message {
        return DB::transaction(function () use ($conversation, $sender, $body, $type, $metadata, $replyTo) {
            $conversation->loadMissing(['buyer', 'seller', 'participants']);

            if (! $conversation->is_group) {
                $other = $conversation->otherParticipant($sender);
                if (UserBlockService::isBlockedEitherWay($sender, $other)) {
                    abort(403, 'Messaging is blocked between these accounts.');
                }
            } else {
                abort_unless($conversation->involves($sender), 403);
            }

            if ($replyTo) {
                abort_unless($replyTo->conversation_id === $conversation->id, 422, 'Invalid reply target.');
                $replyTo->loadMissing('sender:id,name,deleted_at');

                $replyBody = match ($replyTo->type) {
                    MessageType::Image => $replyTo->body ?: 'Photo',
                    MessageType::Video => $replyTo->body ?: 'Video',
                    MessageType::Voice => 'Voice message',
                    MessageType::Product => $replyTo->body
                        ?: ($replyTo->metadata['product']['name'] ?? 'Product'),
                    MessageType::Transfer => $replyTo->body ?: 'Money transfer',
                    MessageType::File => $replyTo->body
                        ?: ($replyTo->metadata['file_name'] ?? 'File'),
                    default => $replyTo->body ?? '',
                };

                $replyMeta = [
                    'id' => $replyTo->id,
                    'type' => $replyTo->type->value,
                    'body' => $replyBody,
                    'sender_name' => $replyTo->sender->name ?? 'User',
                ];

                // Keep product card details so replies show the item, not only text.
                if ($replyTo->type === MessageType::Product) {
                    $product = $replyTo->metadata['product'] ?? null;
                    if (is_array($product)) {
                        $replyMeta['product'] = [
                            'id' => $product['id'] ?? null,
                            'name' => $product['name'] ?? $replyBody,
                            'slug' => $product['slug'] ?? null,
                            'price' => $product['price'] ?? null,
                            'image_url' => $product['image_url'] ?? null,
                        ];
                    }
                }

                $metadata = array_merge($metadata ?? [], [
                    'reply_to' => $replyMeta,
                ]);
            }

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'type' => $type,
                'body' => $body,
                'metadata' => $metadata,
            ]);

            $isCallSignal = in_array($type, [
                MessageType::CallOffer,
                MessageType::CallAnswer,
                MessageType::CallIce,
                MessageType::CallEnd,
            ], true);

            // ICE candidates are high-frequency; never bump the inbox or hit Reverb.
            // Polling delivers them. Sync broadcast on ICE storms exhausts PHP-FPM
            // and takes the whole site down (ERR_CONNECTION_ABORTED).
            if ($type === MessageType::CallIce) {
                return $message->setRelation('sender', $sender);
            }

            if (! $isCallSignal) {
                $conversation->update(['last_message_at' => now()]);
            } elseif (in_array($type, [MessageType::CallOffer, MessageType::CallEnd], true)) {
                // Offer/end may surface in recent activity; answer/ice stay quiet.
                $conversation->update(['last_message_at' => now()]);
            }

            // New real messages bring a deleted/hidden chat back for both people.
            if (! $isCallSignal && $type !== MessageType::CallLog) {
                $conversation->clearHiddenForAll();
            }

            $recipients = $conversation->otherParticipants($sender);

            // QR payments fire their own wallet bell notice — skip duplicate "New message".
            $skipBell = ($metadata['transfer']['via'] ?? null) === 'qr';

            if (! $isCallSignal && $type !== MessageType::CallLog && ! $skipBell && $type !== MessageType::System) {
                $notificationBody = match (true) {
                    $type === MessageType::Text => $body,
                    $type === MessageType::Image => ! empty($metadata['view_once']) ? 'Sent a view once photo' : 'Sent a photo',
                    $type === MessageType::Video => ! empty($metadata['view_once']) ? 'Sent a view once video' : 'Sent a video',
                    $type === MessageType::Voice => 'Sent a voice message',
                    $type === MessageType::Product => 'Shared a product: '.$body,
                    $type === MessageType::Transfer => static::transferPreviewForViewer(
                        $body ?: 'Money transfer',
                        isSender: false,
                    ),
                    $type === MessageType::File => 'Sent a file'.($body !== '' ? ": {$body}" : ''),
                    default => 'New activity',
                };

                $title = $conversation->is_group
                    ? (($conversation->name ?: 'Group').': '.$sender->name)
                    : ($type === MessageType::Transfer ? 'Money received' : 'New message');

                foreach ($recipients as $recipient) {
                    if ($type === MessageType::Transfer && ! $conversation->is_group) {
                        AppNotificationService::send(
                            $recipient,
                            'payment',
                            'Money received',
                            $notificationBody,
                            [
                                'conversation_id' => $conversation->id,
                                'sender_id' => $sender->id,
                                'sender_name' => $sender->name,
                                'reference' => $metadata['transfer']['reference'] ?? null,
                            ],
                        );
                    } else {
                        AppNotificationService::send(
                            $recipient,
                            'message',
                            $title,
                            $notificationBody,
                            [
                                'conversation_id' => $conversation->id,
                                'sender_id' => $sender->id,
                                'sender_name' => $sender->name,
                                'is_group' => $conversation->is_group,
                            ],
                        );
                    }
                }
            }

            if ($type === MessageType::CallOffer && ! $conversation->is_group) {
                // Push after the HTTP response so offer signalling isn't blocked
                // on FCM (can take several seconds per device token).
                $recipient = $recipients->first();
                if ($recipient) {
                    $recipientId = $recipient->id;
                    $senderId = $sender->id;
                    $senderName = $sender->name;
                    $conversationId = $conversation->id;
                    dispatch(function () use ($recipientId, $senderId, $senderName, $conversationId) {
                        $recipient = User::query()->find($recipientId);
                        if (! $recipient) {
                            return;
                        }
                        AppNotificationService::send(
                            $recipient,
                            'call',
                            'Incoming call',
                            "{$senderName} is calling you",
                            [
                                'conversation_id' => $conversationId,
                                'sender_id' => $senderId,
                                'sender_name' => $senderName,
                            ],
                        );
                    })->afterResponse();
                }
            }

            try {
                broadcast(new ChatMessageSent($message->load('sender')))->toOthers();
            } catch (\Throwable) {
                // Message is saved; real-time delivery works when Reverb is running
            }

            return $message;
        });
    }

    public static function canEditMessage(Message $message, User $user): bool
    {
        if ($message->sender_id !== $user->id
            || $message->type !== MessageType::Text
            || ! empty($message->metadata['deleted_at'])) {
            return false;
        }

        $created = $message->created_at ?? now();

        return $created->gt(now()->subMinutes(static::EDIT_WINDOW_MINUTES));
    }

    public static function canReactToMessage(Message $message): bool
    {
        return empty($message->metadata['deleted_at'])
            && empty($message->metadata['view_once'])
            && in_array($message->type, [
                MessageType::Text,
                MessageType::Image,
                MessageType::Video,
                MessageType::Voice,
                MessageType::Product,
                MessageType::Transfer,
                MessageType::File,
            ], true);
    }

    /** Own chat content (text/photo/video/voice) can be removed before the other person reads it. */
    public static function canDeleteMessage(Message $message, User $user): bool
    {
        return $message->sender_id === $user->id
            && in_array($message->type, [
                MessageType::Text,
                MessageType::Image,
                MessageType::Video,
                MessageType::Voice,
                MessageType::Product,
                MessageType::File,
            ], true)
            && $message->read_at === null
            && empty($message->metadata['deleted_at']);
    }

    /** @deprecated Use canEditMessage / canDeleteMessage */
    public static function canModifyMessage(Message $message, User $user): bool
    {
        return static::canEditMessage($message, $user);
    }

    public static function updateMessage(Message $message, User $user, string $body): Message
    {
        abort_unless(
            static::canEditMessage($message, $user),
            422,
            'You can only edit a message within 2 minutes of sending.',
        );

        $metadata = $message->metadata ?? [];
        $metadata['edited_at'] = now()->toIso8601String();

        $message->update([
            'body' => $body,
            'metadata' => $metadata,
        ]);

        $fresh = $message->fresh(['sender:id,name,deleted_at', 'reactions']);
        static::broadcastMessageUpdated($fresh);

        return $fresh;
    }

    public static function deleteMessage(Message $message, User $user): Message
    {
        abort_unless(static::canDeleteMessage($message, $user), 422, 'This message can no longer be deleted.');

        $metadata = $message->metadata ?? [];
        $metadata['deleted_at'] = now()->toIso8601String();

        $message->update([
            'body' => null,
            'metadata' => $metadata,
        ]);

        $fresh = $message->fresh(['sender:id,name,deleted_at', 'reactions']);
        static::broadcastMessageUpdated($fresh);

        return $fresh;
    }

    public static function reactToMessage(Message $message, User $user, string $emoji): Message
    {
        abort_unless(static::canReactToMessage($message), 422, 'You cannot react to this message.');

        $emoji = trim($emoji);
        if ($emoji === '' || mb_strlen($emoji) > 64) {
            abort(422, 'Choose an emoji.');
        }

        $existing = MessageReaction::query()
            ->where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->emoji === $emoji) {
            $existing->delete();
        } else {
            MessageReaction::updateOrCreate(
                ['message_id' => $message->id, 'user_id' => $user->id],
                ['emoji' => $emoji],
            );
        }

        $message->touch();
        $fresh = $message->fresh(['sender:id,name,deleted_at', 'reactions']);
        static::broadcastMessageUpdated($fresh);

        return $fresh;
    }

    public static function broadcastMessageUpdated(?Message $message): void
    {
        if (! $message) {
            return;
        }

        try {
            if (! $message->relationLoaded('sender')) {
                $message->load('sender:id,name,deleted_at');
            }
            if (! $message->relationLoaded('reactions')) {
                $message->load('reactions');
            }
            broadcast(new ChatMessageUpdated($message))->toOthers();
        } catch (\Throwable) {
            // Edit/react still saved; poll will pick it up.
        }
    }

    /**
     * Copy a message into 1:1 chats with selected people.
     * In a group: recipients must be members of that group.
     * In a direct chat: recipients must share a group with the sender (buyer network).
     *
     * @param  list<int>  $memberIds
     * @return array{sent: int, conversation_ids: list<int>}
     */
    public static function forwardToMembers(Conversation $conversation, Message $message, User $actor, array $memberIds): array
    {
        abort_unless($conversation->involves($actor), 403);
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless(empty($message->metadata['deleted_at']), 422, 'This message can no longer be forwarded.');
        abort_unless(empty($message->metadata['view_once']), 422, 'View once messages cannot be forwarded.');
        abort_unless(in_array($message->type, [
            MessageType::Text,
            MessageType::Image,
            MessageType::Video,
            MessageType::Voice,
            MessageType::Product,
            MessageType::File,
        ], true), 422, 'This message cannot be forwarded.');

        if ($conversation->is_group) {
            $conversation->loadMissing('participants');
            $allowedIds = $conversation->participants->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            $allowedIds = static::sharedGroupContactIds($actor);
            if ($allowedIds === []) {
                abort(422, 'Join a group chat first to forward messages to members.');
            }
        }

        $ids = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->filter(fn (int $id) => $id > 0 && $id !== (int) $actor->id && in_array($id, $allowedIds, true))
            ->values();

        if ($ids->isEmpty()) {
            abort(422, $conversation->is_group
                ? 'Choose at least one group member.'
                : 'Choose people from your group chats.');
        }

        if ($ids->count() > 49) {
            abort(422, 'Choose fewer members.');
        }

        $members = User::query()->whereIn('id', $ids)->get();
        if ($members->count() !== $ids->count()) {
            abort(422, 'One or more members could not be found.');
        }

        $metadata = $message->metadata ?? [];
        unset($metadata['reply_to'], $metadata['deleted_at'], $metadata['edited_at']);
        $metadata['forwarded'] = true;

        $body = (string) ($message->body ?? '');
        $sent = 0;
        $conversationIds = [];

        foreach ($members as $member) {
            if (UserBlockService::isBlockedEitherWay($actor, $member)) {
                continue;
            }

            $direct = static::findOrCreateConversation($actor, $member);
            static::sendMessage($direct, $actor, $body, $message->type, $metadata);
            $sent++;
            $conversationIds[] = $direct->id;
        }

        if ($sent === 0) {
            abort(422, 'Could not forward to the selected members.');
        }

        return [
            'sent' => $sent,
            'conversation_ids' => $conversationIds,
        ];
    }

    /**
     * People the user can forward to from a direct chat: members of any shared group.
     *
     * @return list<int>
     */
    public static function sharedGroupContactIds(User $actor): array
    {
        $groupIds = \Illuminate\Support\Facades\DB::table('conversation_participants')
            ->join('conversations', 'conversations.id', '=', 'conversation_participants.conversation_id')
            ->where('conversation_participants.user_id', $actor->id)
            ->where('conversations.is_group', true)
            ->pluck('conversations.id');

        if ($groupIds->isEmpty()) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('conversation_participants')
            ->whereIn('conversation_id', $groupIds)
            ->where('user_id', '!=', $actor->id)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string, avatar: ?string}>
     */
    public static function forwardTargets(User $actor): array
    {
        $ids = static::sharedGroupContactIds($actor);
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name', 'avatar'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->displayAvatarPath(),
            ])
            ->values()
            ->all();
    }

    /**
     * One-tap media: the listing never includes the file URL. Recipients call
     * openViewOnce() to receive it once; after that the bubble stays "Opened".
     *
     * @return array{message: array<string, mixed>, image_url: ?string, video_url: ?string}
     */
    public static function openViewOnce(Message $message, User $viewer): array
    {
        $metadata = $message->metadata ?? [];
        abort_unless(empty($metadata['deleted_at']), 422, 'This message is no longer available.');
        abort_unless(! empty($metadata['view_once']), 422, 'This is not a view once message.');
        abort_unless(in_array($message->type, [MessageType::Image, MessageType::Video], true), 422, 'This message cannot be opened.');
        abort_if((int) $message->sender_id === (int) $viewer->id, 422, 'You already sent this view once message.');

        $viewedBy = collect($metadata['viewed_by'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();
        abort_if(in_array((int) $viewer->id, $viewedBy, true), 422, 'This was already opened.');

        $imageUrl = static::publicMediaUrl($metadata['image_url'] ?? null, $metadata['image_path'] ?? null);
        $videoUrl = static::publicMediaUrl($metadata['video_url'] ?? null, $metadata['video_path'] ?? null);
        abort_if($imageUrl === null && $videoUrl === null, 422, 'This media is no longer available.');

        $viewedBy[] = (int) $viewer->id;
        $metadata['viewed_by'] = array_values(array_unique($viewedBy));
        $metadata['viewed_at'] = $metadata['viewed_at'] ?? now()->toIso8601String();
        $message->metadata = $metadata;
        $message->save();

        $message->loadMissing(['sender:id,name,deleted_at', 'reactions']);
        static::broadcastMessageUpdated($message);

        return [
            'message' => static::formatMessage($message, $viewer),
            'image_url' => $imageUrl,
            'video_url' => $videoUrl,
        ];
    }

    /**
     * Conversation-list / notification preview for the latest visible message.
     */
    public static function inboxPreviewBody(Message $latest, User $viewer): string
    {
        $metadata = $latest->metadata ?? [];
        if (! empty($metadata['view_once']) && in_array($latest->type, [MessageType::Image, MessageType::Video], true)) {
            $kind = $latest->type === MessageType::Video ? 'video' : 'photo';
            $opened = ! empty($metadata['viewed_at']) || ! empty($metadata['viewed_by']);

            return $opened ? 'Opened' : 'View once '.$kind;
        }

        return match ($latest->type) {
            MessageType::Product => 'Product: '.($latest->body ?: ($metadata['product']['name'] ?? 'Shared a product')),
            MessageType::Transfer => static::transferPreviewForMessage($latest, $viewer),
            MessageType::File => $latest->body ?: ($metadata['file_name'] ?? 'File'),
            MessageType::Image => $latest->body ?: 'Photo',
            MessageType::Video => $latest->body ?: 'Video',
            MessageType::Voice => 'Voice message',
            MessageType::System => $latest->body ?: 'Group update',
            default => (string) ($latest->body ?? ''),
        };
    }

    public static function formatMessage(Message $message, ?User $viewer = null): array
    {
        $metadata = $message->metadata ?? [];
        $deleted = ! empty($metadata['deleted_at']);
        $viewOnce = ! empty($metadata['view_once']);
        $viewedBy = collect($metadata['viewed_by'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->all();
        $anyoneOpened = ! empty($metadata['viewed_at']) || $viewedBy !== [];
        $isSender = $viewer && (int) $viewer->id === (int) $message->sender_id;
        $openedForViewer = $isSender
            ? $anyoneOpened
            : ($viewer !== null && in_array((int) $viewer->id, $viewedBy, true));
        $hideMedia = $deleted || $viewOnce;
        $safeMeta = $metadata;
        if ($viewOnce) {
            unset(
                $safeMeta['image_url'],
                $safeMeta['image_path'],
                $safeMeta['video_url'],
                $safeMeta['video_path'],
            );
        }

        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'type' => $message->type->value,
            'body' => $deleted ? null : $message->body,
            'metadata' => $safeMeta,
            'view_once' => $viewOnce,
            'view_once_opened' => $viewOnce && $openedForViewer,
            'image_url' => $hideMedia ? null : static::publicMediaUrl(
                $metadata['image_url'] ?? null,
                $metadata['image_path'] ?? null,
            ),
            'video_url' => $hideMedia ? null : static::publicMediaUrl(
                $metadata['video_url'] ?? null,
                $metadata['video_path'] ?? null,
            ),
            'voice_url' => $deleted ? null : static::publicMediaUrl(
                $metadata['voice_url'] ?? null,
                $metadata['voice_path'] ?? null,
            ),
            'product' => $deleted ? null : ($metadata['product'] ?? null),
            'transfer' => $deleted ? null : ($metadata['transfer'] ?? null),
            'file_url' => $deleted ? null : static::publicMediaUrl(
                $metadata['file_url'] ?? null,
                $metadata['file_path'] ?? null,
            ),
            'file_name' => $deleted ? null : ($metadata['file_name'] ?? null),
            'file_size' => $deleted
                ? null
                : (isset($metadata['file_size']) ? (int) $metadata['file_size'] : null),
            'file_mime' => $deleted ? null : ($metadata['file_mime'] ?? null),
            'duration_seconds' => $deleted
                ? null
                : (isset($metadata['duration_seconds']) ? (int) $metadata['duration_seconds'] : null),
            'call_log' => $metadata['call_log'] ?? null,
            'read_at' => $message->read_at?->toIso8601String(),
            'reply_to' => $metadata['reply_to'] ?? null,
            'edited_at' => $metadata['edited_at'] ?? null,
            'is_deleted' => $deleted,
            'can_edit' => $viewer ? static::canEditMessage($message, $viewer) : false,
            'can_delete' => $viewer ? static::canDeleteMessage($message, $viewer) : false,
            'reactions' => $deleted ? [] : static::formatReactions($message, $viewer),
            'created_at' => $message->created_at?->toIso8601String(),
            'sender' => [
                'id' => $message->sender_id,
                'name' => ($message->sender && ! $message->sender->trashed())
                    ? ($message->sender->name ?: 'User')
                    : 'Deleted account',
            ],
        ];
    }

    /**
     * Admin oversight — keep media URLs even for view-once messages.
     *
     * @return array<string, mixed>
     */
    public static function formatMessageForAdmin(Message $message): array
    {
        $formatted = static::formatMessage($message);
        if (! empty($message->metadata['view_once']) && empty($message->metadata['deleted_at'])) {
            $metadata = $message->metadata ?? [];
            $formatted['image_url'] = static::publicMediaUrl(
                $metadata['image_url'] ?? null,
                $metadata['image_path'] ?? null,
            );
            $formatted['video_url'] = static::publicMediaUrl(
                $metadata['video_url'] ?? null,
                $metadata['video_path'] ?? null,
            );
        }

        return $formatted;
    }

    /**
     * Chat-list / notification copy for wallet transfers.
     * Sender keeps "Transferred GH₵…"; receiver sees "Transferred to you GH₵…".
     */
    public static function transferPreviewForViewer(?string $body, bool $isSender): string
    {
        $text = trim((string) $body);
        if ($text === '') {
            return $isSender ? 'Transferred' : 'Transferred to you';
        }

        if ($isSender) {
            return $text;
        }

        if (str_starts_with($text, 'Transferred to you')) {
            return $text;
        }

        if (str_starts_with($text, 'Transferred ')) {
            return 'Transferred to you '.substr($text, strlen('Transferred '));
        }

        if (strcasecmp($text, 'Transferred') === 0 || strcasecmp($text, 'Money transfer') === 0 || strcasecmp($text, 'Sent money') === 0) {
            return 'Transferred to you';
        }

        return 'Transferred to you — '.$text;
    }

    public static function transferPreviewForMessage(Message $message, User $viewer): string
    {
        return static::transferPreviewForViewer(
            $message->body ?: 'Money transfer',
            isSender: (int) $message->sender_id === (int) $viewer->id,
        );
    }

    /**
     * Prefer the stored path so media keeps working when APP_URL changes.
     * Fall back to a stored URL, rewriting /storage/... under the current host.
     */
    public static function publicMediaUrl(?string $url, ?string $path = null): ?string
    {
        if (is_string($path) && trim($path) !== '') {
            return Storage::disk('public')->url(ltrim($path, '/'));
        }

        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        if (preg_match('#(?:^|/)storage/(.+)$#', $url, $matches) === 1) {
            return Storage::disk('public')->url($matches[1]);
        }

        return $url;
    }

    public static function recordCallLog(
        Conversation $conversation,
        User $endedBy,
        string $status,
        int $callerId,
        string $callerName,
        int $durationSeconds = 0,
        string $callKind = 'voice',
    ): Message {
        $kind = $callKind === 'video' ? 'video' : 'voice';

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $endedBy->id,
            'type' => MessageType::CallLog,
            'body' => $kind === 'video' ? 'Video call' : 'Voice call',
            'metadata' => [
                'call_log' => [
                    'status' => $status,
                    'caller_id' => $callerId,
                    'caller_name' => $callerName,
                    'ended_by_id' => $endedBy->id,
                    'duration_seconds' => $durationSeconds,
                    'call_kind' => $kind,
                ],
            ],
        ]);

        $conversation->update(['last_message_at' => now()]);

        try {
            broadcast(new ChatMessageSent($message->load('sender')))->toOthers();
        } catch (\Throwable) {
            //
        }

        return $message;
    }

    public static function markConversationRead(Conversation $conversation, User $user): void
    {
        // Only mark timeline messages — never call-signalling rows the UI hides.
        Message::where('conversation_id', $conversation->id)
            ->whereIn('type', static::visibleTypes())
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->where('data->conversation_id', $conversation->id)
            ->update(['read_at' => now()]);
    }

    /**
     * Mark only messages the client was actually given (avoids blue ticks on
     * product cards the other person never rendered).
     *
     * @param  array<int, int>  $messageIds
     */
    public static function markMessagesRead(Conversation $conversation, User $user, array $messageIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $messageIds))));
        if ($ids === []) {
            return;
        }

        Message::where('conversation_id', $conversation->id)
            ->whereIn('id', $ids)
            ->whereIn('type', static::visibleTypes())
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->where('data->conversation_id', $conversation->id)
            ->update(['read_at' => now()]);
    }

    /**
     * Newest timeline window for opening a chat (not the oldest 100 — that hid
     * recent voice/text once call logs filled the early history).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public static function threadMessagesFor(Conversation $conversation, User $viewer, int $limit = 100)
    {
        return $conversation->messages()
            ->whereIn('type', static::visibleTypes())
            ->with(['sender:id,name,deleted_at', 'reactions'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->sortBy('id')
            ->values()
            ->map(fn (Message $m) => static::formatMessage($m, $viewer));
    }

    /**
     * New rows after $afterId, plus any still-unread messages for this viewer
     * (fills gaps when realtime delivered a later id first and skipped a product card).
     *
     * @return Collection<int, Message>
     */
    public static function pollVisibleMessages(Conversation $conversation, User $viewer, int $afterId = 0)
    {
        return $conversation->messages()
            ->whereIn('type', static::visibleTypes())
            ->with(['sender:id,name,deleted_at', 'reactions'])
            ->where(function ($q) use ($afterId, $viewer) {
                if ($afterId > 0) {
                    $q->where('id', '>', $afterId);
                } else {
                    // after=0 means "give me nothing historical here" — callers
                    // load the thread via show(); poll only streams forward + unread.
                    $q->whereRaw('0 = 1');
                }

                $q->orWhere(function ($unread) use ($viewer) {
                    $unread->where('sender_id', '!=', $viewer->id)
                        ->whereNull('read_at');
                });
            })
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    /**
     * Already-seen rows that were edited or reacted to after $since.
     *
     * @return Collection<int, Message>
     */
    public static function pollUpdatedMessages(Conversation $conversation, User $viewer, int $afterId, ?\DateTimeInterface $since)
    {
        if ($afterId <= 0 || ! $since) {
            return collect();
        }

        return $conversation->messages()
            ->whereIn('type', static::visibleTypes())
            ->with(['sender:id,name,deleted_at', 'reactions'])
            ->where('id', '<=', $afterId)
            ->where('updated_at', '>', $since)
            ->orderBy('id')
            ->limit(100)
            ->get();
    }

    /**
     * @return list<array{emoji: string, count: int, mine: bool, user_ids: list<int>}>
     */
    public static function formatReactions(Message $message, ?User $viewer = null): array
    {
        $reactions = $message->relationLoaded('reactions')
            ? $message->reactions
            : $message->reactions()->get();

        if ($reactions->isEmpty()) {
            return [];
        }

        return $reactions
            ->groupBy('emoji')
            ->map(function ($group, $emoji) use ($viewer) {
                $userIds = $group->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all();

                return [
                    'emoji' => (string) $emoji,
                    'count' => $group->count(),
                    'mine' => $viewer ? in_array((int) $viewer->id, $userIds, true) : false,
                    'user_ids' => $userIds,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * WebRTC signalling rows are excluded from visibleTypes (and from unread),
     * so poll must fetch them separately when Reverb is down or lagging.
     *
     * When afterId is 0 (fresh chat open / empty thread) we still return the
     * last few minutes of signalling — otherwise a ringing offer is invisible
     * until some later visible message advances the cursor.
     *
     * @return Collection<int, Message>
     */
    public static function pollCallSignals(Conversation $conversation, int $afterId = 0)
    {
        $types = [
            MessageType::CallOffer,
            MessageType::CallAnswer,
            MessageType::CallIce,
            MessageType::CallEnd,
        ];

        $fresh = $conversation->messages()
            ->whereIn('type', $types)
            ->with('sender:id,name,deleted_at')
            ->when(
                $afterId > 0,
                fn ($q) => $q->where('id', '>', $afterId),
                fn ($q) => $q->where('created_at', '>=', now()->subMinutes(3)),
            )
            ->orderBy('id')
            ->limit(80)
            ->get();

        // Re-surface an unanswered offer from the ring window even if the
        // client's cursor already jumped past it (push open / missed WS event).
        $liveOffer = $conversation->messages()
            ->where('type', MessageType::CallOffer)
            ->where('created_at', '>=', now()->subSeconds(120))
            ->orderByDesc('id')
            ->first();

        if (! $liveOffer) {
            return $fresh;
        }

        $settled = $conversation->messages()
            ->whereIn('type', [MessageType::CallEnd, MessageType::CallAnswer])
            ->where('id', '>', $liveOffer->id)
            ->exists();

        if ($settled || $fresh->contains(fn (Message $m) => (int) $m->id === (int) $liveOffer->id)) {
            return $fresh;
        }

        $tail = $conversation->messages()
            ->whereIn('type', $types)
            ->with('sender:id,name,deleted_at')
            ->where('id', '>=', $liveOffer->id)
            ->orderBy('id')
            ->limit(80)
            ->get();

        return $fresh->concat($tail)->unique('id')->sortBy('id')->values();
    }

    /**
     * Own messages the peer has read — capped so poll stays cheap during calls.
     *
     * @return list<int>
     */
    public static function recentReadMessageIds(Conversation $conversation, User $viewer): array
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', $viewer->id)
            ->whereNotNull('read_at')
            ->orderByDesc('id')
            ->limit(200)
            ->pluck('id')
            ->all();
    }

    public static function unreadMessageCount(User $user): int
    {
        return Message::whereHas('conversation', function ($q) use ($user) {
            $q->where(function ($visible) use ($user) {
                $visible->where(function ($direct) use ($user) {
                    $direct->where('is_group', false)
                        ->where(fn ($pair) => $pair->where('buyer_id', $user->id)->orWhere('seller_id', $user->id));
                })->orWhere(function ($group) use ($user) {
                    $group->where('is_group', true)
                        ->whereHas('participantRows', fn ($p) => $p->where('user_id', $user->id));
                });
            });
        })
            ->whereIn('type', static::visibleTypes())
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public static function unreadNotificationCount(User $user): int
    {
        return AppNotification::where('user_id', $user->id)->whereNull('read_at')->count();
    }
}
