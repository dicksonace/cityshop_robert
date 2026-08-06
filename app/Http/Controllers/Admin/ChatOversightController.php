<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\UserBlockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatOversightController extends Controller
{
    public function index(Request $request): Response
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
            ->paginate(20)
            ->withQueryString()
            ->through(function (Conversation $chat) {
                $blocked = false;
                if ($chat->buyer && $chat->seller) {
                    $blocked = UserBlockService::isBlockedEitherWay($chat->buyer, $chat->seller);
                }

                return [
                    'id' => $chat->id,
                    'last_message_at' => $chat->last_message_at?->toIso8601String(),
                    'blocked' => $blocked,
                    'buyer' => $chat->buyer ? [
                        'id' => $chat->buyer->id,
                        'name' => $chat->buyer->name,
                        'email' => $chat->buyer->email,
                        'mobile' => $chat->buyer->mobile,
                    ] : null,
                    'seller' => $chat->seller ? [
                        'id' => $chat->seller->id,
                        'name' => $chat->seller->name,
                        'email' => $chat->seller->email,
                        'mobile' => $chat->seller->mobile,
                    ] : null,
                    'product' => $chat->product ? [
                        'id' => $chat->product->id,
                        'name' => $chat->product->name,
                        'slug' => $chat->product->slug,
                    ] : null,
                    'latest_message' => $chat->latestVisibleMessage ? [
                        'body' => $chat->latestVisibleMessage->body,
                        'type' => $chat->latestVisibleMessage->type?->value,
                        'sender' => $chat->latestVisibleMessage->sender
                            ? ['name' => $chat->latestVisibleMessage->sender->name]
                            : null,
                    ] : null,
                ];
            });

        return Inertia::render('admin/chats/index', [
            'conversations' => $conversations,
            'search' => $search !== '' ? $search : null,
        ]);
    }

    public function show(Conversation $conversation): Response
    {
        $conversation->load([
            'buyer:id,name,email,mobile',
            'seller:id,name,email,mobile',
            'product:id,name,slug',
        ]);

        $messages = $conversation->messages()
            ->with('sender:id,name,role')
            ->oldest()
            ->paginate(50)
            ->withQueryString();

        $blocked = $conversation->buyer && $conversation->seller
            ? UserBlockService::isBlockedEitherWay($conversation->buyer, $conversation->seller)
            : false;

        return Inertia::render('admin/chats/show', [
            'conversation' => $conversation,
            'messages' => $messages,
            'blocked' => $blocked,
        ]);
    }
}
