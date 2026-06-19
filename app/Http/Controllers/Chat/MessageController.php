<?php

namespace App\Http\Controllers\Chat;

use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = ChatService::sendMessage($conversation, $request->user(), $validated['body']);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'sender_id' => $message->sender_id,
                'type' => $message->type->value,
                'body' => $message->body,
                'metadata' => $message->metadata,
                'created_at' => $message->created_at?->toIso8601String(),
                'sender' => ['id' => $request->user()->id, 'name' => $request->user()->name],
            ],
        ]);
    }

    public function signal(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $validated = $request->validate([
            'type' => ['required', 'in:call_offer,call_answer,call_ice,call_end'],
            'body' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array'],
        ]);

        $type = MessageType::from($validated['type']);

        $message = ChatService::sendMessage(
            $conversation,
            $request->user(),
            $validated['body'] ?? '',
            $type,
            $validated['metadata'] ?? null,
        );

        return response()->json(['ok' => true, 'message_id' => $message->id]);
    }
}
