<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Events\ChatMessageSent;
use App\Events\UserPresenceChanged;
use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ChatService
{
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

    public static function touchPresence(User $user): void
    {
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
            return $existing;
        }

        // Do not link product_id here — that would show the item to the seller
        // before the buyer chooses to send it. Product is attached only when shared.
        return Conversation::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
        ]);
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
            $conversation->loadMissing(['buyer', 'seller']);
            $other = $conversation->otherParticipant($sender);
            if (UserBlockService::isBlockedEitherWay($sender, $other)) {
                abort(403, 'Messaging is blocked between these accounts.');
            }

            if ($replyTo) {
                abort_unless($replyTo->conversation_id === $conversation->id, 422, 'Invalid reply target.');
                $replyTo->loadMissing('sender:id,name');

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

            $conversation->update(['last_message_at' => now()]);

            $isCallSignal = in_array($type, [
                MessageType::CallOffer,
                MessageType::CallAnswer,
                MessageType::CallIce,
                MessageType::CallEnd,
            ], true);

            // New real messages bring a deleted/hidden chat back for both people.
            if (! $isCallSignal && $type !== MessageType::CallLog) {
                $conversation->clearHiddenForAll();
            }

            $recipient = $conversation->otherParticipant($sender);

            if (! $isCallSignal && $type !== MessageType::CallLog) {
                $isCall = str_starts_with($type->value, 'call');
                $notificationBody = match (true) {
                    $type === MessageType::Text => $body,
                    $type === MessageType::Image => 'Sent a photo',
                    $type === MessageType::Video => 'Sent a video',
                    $type === MessageType::Voice => 'Sent a voice message',
                    $type === MessageType::Product => 'Shared a product: '.$body,
                    $type === MessageType::Transfer => $body ?: 'Sent money',
                    $type === MessageType::File => 'Sent a file'.($body !== '' ? ": {$body}" : ''),
                    default => 'New activity',
                };

                AppNotificationService::send(
                    $recipient,
                    'message',
                    'New message',
                    $notificationBody,
                    [
                        'conversation_id' => $conversation->id,
                        'sender_id' => $sender->id,
                        'sender_name' => $sender->name,
                    ],
                );
            }

            if ($type === MessageType::CallOffer) {
                AppNotificationService::send(
                    $recipient,
                    'call',
                    'Incoming call',
                    "{$sender->name} is calling you",
                    [
                        'conversation_id' => $conversation->id,
                        'sender_id' => $sender->id,
                        'sender_name' => $sender->name,
                    ],
                );
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
        return $message->sender_id === $user->id
            && $message->type === MessageType::Text
            && $message->read_at === null
            && empty($message->metadata['deleted_at']);
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
        abort_unless(static::canEditMessage($message, $user), 422, 'This message can no longer be edited.');

        $metadata = $message->metadata ?? [];
        $metadata['edited_at'] = now()->toIso8601String();

        $message->update([
            'body' => $body,
            'metadata' => $metadata,
        ]);

        return $message->fresh(['sender:id,name']);
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

        return $message->fresh(['sender:id,name']);
    }

    public static function formatMessage(Message $message, ?User $viewer = null): array
    {
        $metadata = $message->metadata ?? [];
        $deleted = ! empty($metadata['deleted_at']);

        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'type' => $message->type->value,
            'body' => $deleted ? null : $message->body,
            'metadata' => $message->metadata,
            'image_url' => $deleted ? null : static::publicMediaUrl(
                $metadata['image_url'] ?? null,
                $metadata['image_path'] ?? null,
            ),
            'video_url' => $deleted ? null : static::publicMediaUrl(
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
            'created_at' => $message->created_at?->toIso8601String(),
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
            ],
        ];
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
     * New rows after $afterId, plus any still-unread messages for this viewer
     * (fills gaps when realtime delivered a later id first and skipped a product card).
     *
     * @return Collection<int, Message>
     */
    public static function pollVisibleMessages(Conversation $conversation, User $viewer, int $afterId = 0)
    {
        return $conversation->messages()
            ->whereIn('type', static::visibleTypes())
            ->with('sender:id,name')
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
            ->get();
    }

    /**
     * WebRTC signalling rows are excluded from visibleTypes (and from unread),
     * so poll must fetch them separately when Reverb is down or lagging.
     *
     * @return Collection<int, Message>
     */
    public static function pollCallSignals(Conversation $conversation, int $afterId = 0)
    {
        if ($afterId <= 0) {
            return collect();
        }

        return $conversation->messages()
            ->whereIn('type', [
                MessageType::CallOffer,
                MessageType::CallAnswer,
                MessageType::CallIce,
                MessageType::CallEnd,
            ])
            ->with('sender:id,name')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->get();
    }

    public static function unreadMessageCount(User $user): int
    {
        return Message::whereHas('conversation', function ($q) use ($user) {
            $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
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
