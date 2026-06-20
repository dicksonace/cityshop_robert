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
use Illuminate\Support\Facades\DB;

class ChatService
{
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
        return Conversation::firstOrCreate(
            ['buyer_id' => $buyer->id, 'seller_id' => $seller->id],
            ['product_id' => $product?->id]
        );
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
            if ($replyTo) {
                abort_unless($replyTo->conversation_id === $conversation->id, 422, 'Invalid reply target.');
                $replyBody = $replyTo->type === MessageType::Image
                    ? ($replyTo->body ?: 'Photo')
                    : ($replyTo->body ?? '');
                $metadata = array_merge($metadata ?? [], [
                    'reply_to' => [
                        'id' => $replyTo->id,
                        'body' => $replyBody,
                        'sender_name' => $replyTo->sender->name ?? 'User',
                    ],
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

            $recipient = $conversation->otherParticipant($sender);

            $isCallSignal = in_array($type, [
                MessageType::CallOffer,
                MessageType::CallAnswer,
                MessageType::CallIce,
                MessageType::CallEnd,
            ], true);

            if (! $isCallSignal && $type !== MessageType::CallLog) {
                $isCall = str_starts_with($type->value, 'call');
                $notificationBody = match (true) {
                    $type === MessageType::Text => $body,
                    $type === MessageType::Image => 'Sent a photo',
                    default => 'New activity',
                };

                AppNotification::create([
                    'user_id' => $recipient->id,
                    'type' => 'message',
                    'title' => 'New message',
                    'body' => $notificationBody,
                    'data' => [
                        'conversation_id' => $conversation->id,
                        'sender_id' => $sender->id,
                        'sender_name' => $sender->name,
                    ],
                ]);
            }

            if ($type === MessageType::CallOffer) {
                AppNotification::create([
                    'user_id' => $recipient->id,
                    'type' => 'call',
                    'title' => 'Incoming call',
                    'body' => "{$sender->name} is calling you",
                    'data' => [
                        'conversation_id' => $conversation->id,
                        'sender_id' => $sender->id,
                        'sender_name' => $sender->name,
                    ],
                ]);
            }

            try {
                broadcast(new ChatMessageSent($message->load('sender')))->toOthers();
            } catch (\Throwable) {
                // Message is saved; real-time delivery works when Reverb is running
            }

            return $message;
        });
    }

    public static function canModifyMessage(Message $message, User $user): bool
    {
        return $message->sender_id === $user->id
            && $message->type === MessageType::Text
            && $message->read_at === null
            && empty($message->metadata['deleted_at']);
    }

    public static function updateMessage(Message $message, User $user, string $body): Message
    {
        abort_unless(static::canModifyMessage($message, $user), 422, 'This message can no longer be edited.');

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
        abort_unless(static::canModifyMessage($message, $user), 422, 'This message can no longer be deleted.');

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
            'image_url' => $metadata['image_url'] ?? null,
            'call_log' => $metadata['call_log'] ?? null,
            'read_at' => $message->read_at?->toIso8601String(),
            'reply_to' => $metadata['reply_to'] ?? null,
            'edited_at' => $metadata['edited_at'] ?? null,
            'is_deleted' => $deleted,
            'can_edit' => $viewer ? static::canModifyMessage($message, $viewer) : false,
            'can_delete' => $viewer ? static::canModifyMessage($message, $viewer) : false,
            'created_at' => $message->created_at?->toIso8601String(),
            'sender' => [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
            ],
        ];
    }

    public static function recordCallLog(
        Conversation $conversation,
        User $endedBy,
        string $status,
        int $callerId,
        string $callerName,
        int $durationSeconds = 0,
    ): Message {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $endedBy->id,
            'type' => MessageType::CallLog,
            'body' => 'Voice call',
            'metadata' => [
                'call_log' => [
                    'status' => $status,
                    'caller_id' => $callerId,
                    'caller_name' => $callerName,
                    'ended_by_id' => $endedBy->id,
                    'duration_seconds' => $durationSeconds,
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
        Message::where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        AppNotification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->where('data->conversation_id', $conversation->id)
            ->update(['read_at' => now()]);
    }

    public static function unreadMessageCount(User $user): int
    {
        return Message::whereHas('conversation', function ($q) use ($user) {
            $q->where('buyer_id', $user->id)->orWhere('seller_id', $user->id);
        })
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public static function unreadNotificationCount(User $user): int
    {
        return AppNotification::where('user_id', $user->id)->whereNull('read_at')->count();
    }
}
