<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse|RedirectResponse
    {
        $userId = $request->user()->id;

        $conversations = Conversation::with([
            'buyer:id,name,city,region,last_seen_at',
            'seller:id,name,city,region,last_seen_at',
            'seller.sellerProfile:id,user_id,business_name,store_name,slug',
            'product:id,name,slug',
            'latestMessage.sender:id,name',
        ])
            ->where(fn ($q) => $q->where('buyer_id', $userId)->orWhere('seller_id', $userId))
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Conversation $c) => $this->formatConversation($c, $request->user()));

        if ($request->wantsJson()) {
            return response()->json(['conversations' => $conversations]);
        }

        return back()->with('openChat', true);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse|RedirectResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        ChatService::markConversationRead($conversation, $request->user());

        $conversation->load([
            'buyer:id,name,city,region,last_seen_at',
            'seller:id,name,city,region,last_seen_at',
            'seller.sellerProfile:id,user_id,business_name,store_name,slug,business_address',
            'product:id,name,slug',
        ]);

        $messages = $conversation->messages()
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($m) => $this->formatMessage($m));

        if ($request->wantsJson()) {
            return response()->json([
                'conversation' => $this->formatConversation($conversation, $request->user(), detailed: true),
                'messages' => $messages,
            ]);
        }

        return back()->with('openChat', true);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:users,id'],
            'product_id' => ['nullable', 'exists:products,id'],
        ]);

        $seller = User::findOrFail($validated['seller_id']);
        $product = isset($validated['product_id']) ? Product::find($validated['product_id']) : null;

        if ($request->user()->id === $seller->id) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'You cannot message yourself.'], 422);
            }

            return back()->with('error', 'You cannot message yourself.');
        }

        $conversation = ChatService::findOrCreateConversation($request->user(), $seller, $product);

        $conversation->load([
            'buyer:id,name,city,region,last_seen_at',
            'seller:id,name,city,region,last_seen_at',
            'seller.sellerProfile:id,user_id,business_name,store_name,slug,business_address',
            'product:id,name,slug',
        ]);

        if ($request->wantsJson()) {
            $messages = $conversation->messages()
                ->with('sender:id,name')
                ->orderBy('created_at')
                ->limit(100)
                ->get()
                ->map(fn ($m) => $this->formatMessage($m));

            return response()->json([
                'conversation' => $this->formatConversation($conversation, $request->user(), detailed: true),
                'messages' => $messages,
            ]);
        }

        return redirect()->route('chat.show', $conversation);
    }

    public function poll(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->involves($request->user()), 403);

        $afterId = (int) $request->get('after', 0);

        $messages = $conversation->messages()
            ->with('sender:id,name')
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => $this->formatMessage($m));

        if ($messages->isNotEmpty()) {
            ChatService::markConversationRead($conversation, $request->user());
        }

        $other = $conversation->otherParticipant($request->user());
        $other->loadMissing('sellerProfile');

        return response()->json([
            'messages' => $messages,
            'other' => [
                'id' => $other->id,
                'name' => $other->name,
                'online' => ChatService::isOnline($other),
                'last_seen_at' => $other->last_seen_at?->toIso8601String(),
                'city' => $other->city,
                'region' => $other->region,
                'seller_profile' => $other->sellerProfile ? [
                    'business_name' => $other->sellerProfile->business_name,
                    'store_name' => $other->sellerProfile->store_name,
                    'business_address' => $other->sellerProfile->business_address,
                ] : null,
            ],
        ]);
    }

    private function formatConversation(Conversation $conversation, User $user, bool $detailed = false): array
    {
        $other = $conversation->otherParticipant($user);
        $other->loadMissing('sellerProfile');

        $latest = $conversation->latestMessage;
        $unread = $conversation->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        $data = [
            'id' => $conversation->id,
            'product' => $conversation->product ? [
                'id' => $conversation->product->id,
                'name' => $conversation->product->name,
                'slug' => $conversation->product->slug,
            ] : null,
            'other' => [
                'id' => $other->id,
                'name' => $other->name,
                'online' => ChatService::isOnline($other),
                'last_seen_at' => $other->last_seen_at?->toIso8601String(),
                'city' => $other->city,
                'region' => $other->region,
                'seller_profile' => $other->sellerProfile ? [
                    'business_name' => $other->sellerProfile->business_name,
                    'store_name' => $other->sellerProfile->store_name,
                    'slug' => $other->sellerProfile->slug,
                    'business_address' => $other->sellerProfile->business_address,
                ] : null,
            ],
            'latest_message' => $latest ? [
                'body' => $latest->body,
                'type' => $latest->type->value,
                'created_at' => $latest->created_at?->toIso8601String(),
                'sender_id' => $latest->sender_id,
            ] : null,
            'unread_count' => $unread,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
        ];

        return $data;
    }

    private function formatMessage($message): array
    {
        return [
            'id' => $message->id,
            'sender_id' => $message->sender_id,
            'type' => $message->type->value,
            'body' => $message->body,
            'metadata' => $message->metadata,
            'created_at' => $message->created_at?->toIso8601String(),
            'sender' => ['id' => $message->sender->id, 'name' => $message->sender->name],
        ];
    }
}
