<?php

namespace App\Events;

use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    public function broadcastWith(): array
    {
        $message = $this->message->relationLoaded('sender')
            ? $this->message
            : $this->message->load('sender:id,name');

        if (! $message->relationLoaded('reactions')) {
            $message->load('reactions');
        }

        return [
            'message' => array_merge(ChatService::formatMessage($message), [
                'conversation_id' => $message->conversation_id,
            ]),
        ];
    }
}
