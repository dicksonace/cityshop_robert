<?php

namespace App\Http\Controllers\Chat;

use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ]);

            $replyTo = null;
        if (! empty($validated['reply_to_id'])) {
            $replyTo = Message::where('conversation_id', $conversation->id)
                ->where('id', $validated['reply_to_id'])
                ->whereIn('type', [MessageType::Text, MessageType::Image])
                ->with('sender:id,name')
                ->firstOrFail();
        }

        $message = ChatService::sendMessage(
            $conversation,
            $request->user(),
            $validated['body'],
            MessageType::Text,
            null,
            $replyTo,
        );

        $message->load('sender:id,name');

        return response()->json([
            'message' => ChatService::formatMessage($message, $request->user()),
        ]);
    }

    public function uploadImage(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $path = $request->file('image')->store('chat/'.$conversation->id, 'public');
        $url = Storage::disk('public')->url($path);

        $message = ChatService::sendMessage(
            $conversation,
            $request->user(),
            $validated['caption'] ?? '',
            MessageType::Image,
            [
                'image_path' => $path,
                'image_url' => $url,
            ],
        );

        $message->load('sender:id,name');

        return response()->json([
            'message' => ChatService::formatMessage($message, $request->user()),
        ]);
    }

    public function update(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);
        abort_unless($message->conversation_id === $conversation->id, 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = ChatService::updateMessage($message, $request->user(), $validated['body']);

        return response()->json([
            'message' => ChatService::formatMessage($message, $request->user()),
        ]);
    }

    public function destroy(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);
        abort_unless($message->conversation_id === $conversation->id, 404);

        $message = ChatService::deleteMessage($message, $request->user());

        return response()->json([
            'message' => ChatService::formatMessage($message, $request->user()),
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
