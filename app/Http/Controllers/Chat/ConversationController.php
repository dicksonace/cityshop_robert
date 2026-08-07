<?php

namespace App\Http\Controllers\Chat;

use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
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
            'buyer:id,name,avatar,city,region,last_seen_at',
            'seller:id,name,avatar,city,region,last_seen_at',
            'seller.sellerProfile:id,user_id,business_name,store_name,slug,shop_photo',
            'product:id,name,slug,price,discount_price',
            'product.images',
            'latestVisibleMessage.sender:id,name',
        ])
            ->where(fn ($q) => $q->where('buyer_id', $userId)->orWhere('seller_id', $userId))
            ->where(function ($q) use ($userId) {
                $q->where(function ($buyer) use ($userId) {
                    $buyer->where('buyer_id', $userId)->whereNull('buyer_hidden_at');
                })->orWhere(function ($seller) use ($userId) {
                    $seller->where('seller_id', $userId)->whereNull('seller_hidden_at');
                });
            })
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
            'buyer:id,name,avatar,city,region,last_seen_at',
            'seller:id,name,avatar,city,region,last_seen_at',
            'seller.sellerProfile:id,user_id,business_name,store_name,slug,business_address,shop_photo',
            'product:id,name,slug,price,discount_price',
            'product.images',
        ]);

        $messages = $conversation->messages()
            ->whereIn('type', ChatService::visibleTypes())
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($m) => ChatService::formatMessage($m, $request->user()));

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

        if ($product && (int) $product->seller_id !== (int) $seller->id) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'That product does not belong to this seller.'], 422);
            }

            return back()->with('error', 'That product does not belong to this seller.');
        }

        if ($request->user()->id === $seller->id) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'You cannot message yourself.'], 422);
            }

            return back()->with('error', 'You cannot message yourself.');
        }

        $conversation = ChatService::findOrCreateConversation($request->user(), $seller, $product);

        $conversation->load([
            'buyer:id,name,avatar,city,region,last_seen_at',
            'seller:id,name,avatar,city,region,last_seen_at',
            'seller.sellerProfile:id,user_id,business_name,store_name,slug,business_address,shop_photo',
            'product:id,name,slug,price,discount_price',
            'product.images',
        ]);

        if ($request->wantsJson()) {
            $messages = $conversation->messages()
                ->whereIn('type', ChatService::visibleTypes())
                ->with('sender:id,name')
                ->orderBy('created_at')
                ->limit(100)
                ->get()
                ->map(fn ($m) => ChatService::formatMessage($m, $request->user()));

            return response()->json([
                'conversation' => $this->formatConversation($conversation, $request->user(), detailed: true),
                'messages' => $messages,
                'attach_product' => $product ? ChatService::productCardPayload($product) : null,
            ]);
        }

        return redirect()->route('chat.show', $conversation);
    }

    public function destroy(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);
        $conversation->hideFor($request->user());

        return response()->json([
            'ok' => true,
            'message' => 'Chat deleted from your inbox.',
        ]);
    }

    public function search(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json(['messages' => []]);
        }

        $messages = $conversation->messages()
            ->whereIn('type', ChatService::visibleTypes())
            ->where('body', 'like', '%'.$q.'%')
            ->with('sender:id,name')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn ($m) => ChatService::formatMessage($m, $request->user()))
            ->values();

        return response()->json(['messages' => $messages]);
    }

    public function poll(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->involves($request->user()), 403);

        $afterId = (int) $request->get('after', 0);

        $polled = ChatService::pollVisibleMessages($conversation, $request->user(), $afterId);
        $signals = ChatService::pollCallSignals($conversation, $afterId);
        $combined = $polled->concat($signals)->unique('id')->sortBy('id')->values();
        $messages = $combined->map(fn ($m) => ChatService::formatMessage($m, $request->user()));

        if ($polled->isNotEmpty()) {
            ChatService::markMessagesRead(
                $conversation,
                $request->user(),
                $polled->pluck('id')->all(),
            );
        }

        $other = $conversation->otherParticipant($request->user());
        $other->loadMissing('sellerProfile');

        $readMessageIds = ChatService::recentReadMessageIds($conversation, $request->user());

        return response()->json([
            'messages' => $messages,
            'read_message_ids' => $readMessageIds,
            'other' => [
                'id' => $other->id,
                'name' => $other->name,
                'avatar' => $other->displayAvatarPath(),
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
        ]);
    }

    private function formatConversation(Conversation $conversation, User $user, bool $detailed = false): array
    {
        $other = $conversation->otherParticipant($user);
        $other->loadMissing('sellerProfile');

        $latest = $conversation->latestVisibleMessage;
        $unread = $conversation->messages()
            ->whereIn('type', ChatService::visibleTypes())
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        $product = ChatService::sharedProductForConversation($conversation);
        $productPayload = $product ? ChatService::productCardPayload($product) : null;

        $data = [
            'id' => $conversation->id,
            'product' => $productPayload,
            'other' => [
                'id' => $other->id,
                'name' => $other->name,
                'avatar' => $other->displayAvatarPath(),
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
                'body' => match ($latest->type) {
                    MessageType::Product => 'Product: '.($latest->body ?: ($latest->metadata['product']['name'] ?? 'Shared a product')),
                    MessageType::Transfer => ChatService::transferPreviewForMessage($latest, $user),
                    default => $latest->body,
                },
                'type' => $latest->type->value,
                'created_at' => $latest->created_at?->toIso8601String(),
                'sender_id' => $latest->sender_id,
                'call_log' => $latest->type === MessageType::CallLog
                    ? ($latest->metadata['call_log'] ?? null)
                    : null,
            ] : null,
            'unread_count' => $unread,
            'last_message_at' => ($latest?->created_at ?? $conversation->last_message_at)?->toIso8601String(),
        ];

        return $data;
    }
}
