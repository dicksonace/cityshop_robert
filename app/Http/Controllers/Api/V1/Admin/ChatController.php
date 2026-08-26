<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use App\Services\UserBlockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();

        $conversations = Conversation::query()
            ->with([
                'buyer:id,name,email,mobile',
                'seller:id,name,email,mobile',
                'product:id,name,slug',
                'latestVisibleMessage.sender:id,name',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('buyer', function ($buyer) use ($search) {
                        $buyer->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    })->orWhereHas('seller', function ($seller) use ($search) {
                        $seller->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    });
                });
            })
            ->latest('last_message_at')
            ->paginate(20);

        return response()->json([
            'data' => $conversations->getCollection()->map(function (Conversation $chat) {
                $latest = $chat->latestVisibleMessage;
                $preview = $latest?->body;
                if ($latest) {
                    $preview = match ($latest->type?->value) {
                        'image' => $latest->body ? "[Photo] {$latest->body}" : '[Photo]',
                        'video' => $latest->body ? "[Video] {$latest->body}" : '[Video]',
                        'voice' => '[Voice message]',
                        'product' => '[Product]',
                        'transfer' => '[Money transfer]',
                        'call_log' => 'Voice call',
                        default => $latest->body,
                    };
                }

                return [
                    'id' => $chat->id,
                    'last_message_at' => $chat->last_message_at?->toIso8601String(),
                    'blocked' => $chat->buyer && $chat->seller
                        ? UserBlockService::isBlockedEitherWay($chat->buyer, $chat->seller)
                        : false,
                    'buyer' => $chat->buyer ? ['id' => $chat->buyer->id, 'name' => $chat->buyer->name, 'mobile' => $chat->buyer->mobile] : null,
                    'seller' => $chat->seller ? ['id' => $chat->seller->id, 'name' => $chat->seller->name, 'mobile' => $chat->seller->mobile] : null,
                    'product' => $chat->product ? ['id' => $chat->product->id, 'name' => $chat->product->name] : null,
                    'latest_message' => $preview,
                ];
            })->values(),
            'meta' => AdminJson::meta($conversations),
        ]);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $conversation->load(['buyer:id,name,email,mobile', 'seller:id,name,email,mobile', 'product:id,name,slug']);

        $messages = $conversation->messages()
            ->with('sender:id,name,role')
            ->oldest()
            ->paginate(50);

        return response()->json([
            'data' => [
                'id' => $conversation->id,
                'buyer' => $conversation->buyer ? ['id' => $conversation->buyer->id, 'name' => $conversation->buyer->name] : null,
                'seller' => $conversation->seller ? ['id' => $conversation->seller->id, 'name' => $conversation->seller->name] : null,
                'blocked' => $conversation->buyer && $conversation->seller
                    ? UserBlockService::isBlockedEitherWay($conversation->buyer, $conversation->seller)
                    : false,
            ],
            'messages' => $messages->getCollection()->map(function (Message $message) {
                $formatted = ChatService::formatMessageForAdmin($message);
                $formatted['sender'] = $message->sender ? [
                    'id' => $message->sender->id,
                    'name' => $message->sender->name,
                    'role' => $message->sender->role?->value,
                ] : null;

                return $formatted;
            })->values(),
            'meta' => AdminJson::meta($messages),
        ]);
    }
}
