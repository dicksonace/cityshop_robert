<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
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
                'seller:id,name,email',
                'product:id,name,slug',
                'latestMessage.sender:id,name',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('buyer', function ($buyer) use ($search) {
                        $buyer->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('mobile', 'like', "%{$search}%");
                    })->orWhereHas('seller', function ($seller) use ($search) {
                        $seller->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                });
            })
            ->latest('last_message_at')
            ->paginate(20)
            ->withQueryString();

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

        return Inertia::render('admin/chats/show', [
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }
}
