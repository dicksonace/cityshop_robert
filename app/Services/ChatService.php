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

    public static function sendMessage(Conversation $conversation, User $sender, string $body, MessageType $type = MessageType::Text, ?array $metadata = null): Message
    {
        return DB::transaction(function () use ($conversation, $sender, $body, $type, $metadata) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'type' => $type,
                'body' => $body,
                'metadata' => $metadata,
            ]);

            $conversation->update(['last_message_at' => now()]);

            $recipient = $conversation->otherParticipant($sender);

            AppNotification::create([
                'user_id' => $recipient->id,
                'type' => $type === MessageType::Text ? 'message' : 'call',
                'title' => $type === MessageType::Text ? 'New message' : 'Incoming call',
                'body' => $type === MessageType::Text ? $body : "{$sender->name} is calling you",
                'data' => [
                    'conversation_id' => $conversation->id,
                    'sender_id' => $sender->id,
                    'sender_name' => $sender->name,
                ],
            ]);

            try {
                broadcast(new ChatMessageSent($message->load('sender')))->toOthers();
            } catch (\Throwable) {
                // Message is saved; real-time delivery works when Reverb is running
            }

            return $message;
        });
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
