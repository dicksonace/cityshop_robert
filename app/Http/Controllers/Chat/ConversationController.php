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
        $conversations = ChatService::visibleConversationsQuery($request->user()->id)
            ->with([
                'buyer:id,name,avatar,city,region,last_seen_at',
                'seller:id,name,avatar,city,region,last_seen_at',
                'seller.sellerProfile:id,user_id,business_name,store_name,slug,shop_photo',
                'participants:id,name,avatar,last_seen_at',
                'product:id,name,slug,price,discount_price',
                'product.images',
                'latestVisibleMessage.sender:id,name',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Conversation $c) => $this->formatConversation($c, $request->user()));

        if ($request->wantsJson()) {
            return response()->json(['conversations' => $conversations]);
        }

        return back()->with('openChat', true);
    }

    public function forwardTargets(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ChatService::forwardTargets($request->user()),
        ]);
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

        $messages = ChatService::threadMessagesFor($conversation, $request->user());

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
            $messages = ChatService::threadMessagesFor($conversation, $request->user());

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

        $other = $conversation->is_group
            ? null
            : $conversation->otherParticipant($request->user());
        if ($other) {
            $other->loadMissing('sellerProfile');
        }

        $readMessageIds = ChatService::recentReadMessageIds($conversation, $request->user());

        return response()->json([
            'messages' => $messages,
            'read_message_ids' => $readMessageIds,
            'is_group' => (bool) $conversation->is_group,
            'other' => $other ? [
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
            ] : [
                'id' => null,
                'name' => $conversation->name ?: 'Group',
                'avatar' => $conversation->avatar,
                ...ChatService::presenceFor($conversation, $request->user()),
                'is_group' => true,
            ],
        ]);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [\App\Enums\UserRole::Buyer, \App\Enums\UserRole::Seller], true), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'member_ids' => ['required', 'array', 'min:1', 'max:49'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $conversation = ChatService::createGroup($user, $validated['name'], $validated['member_ids']);
        $conversation->load([
            'participants:id,name,avatar,last_seen_at',
            'latestVisibleMessage.sender:id,name',
        ]);

        $messages = ChatService::threadMessagesFor($conversation, $user);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $user, detailed: true),
            'messages' => $messages,
        ], 201);
    }

    private function formatConversation(Conversation $conversation, User $user, bool $detailed = false): array
    {
        $latest = $conversation->latestVisibleMessage;
        $unread = $conversation->messages()
            ->whereIn('type', ChatService::visibleTypes())
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        if ($conversation->is_group) {
            $conversation->loadMissing('participants:id,name,avatar,last_seen_at');
            $members = $conversation->participants;
            $onlineCount = $members
                ->filter(fn (User $member) => (int) $member->id !== (int) $user->id && ChatService::isOnline($member))
                ->count();
            $groupAvatar = $conversation->avatar;

            return [
                'id' => $conversation->id,
                'is_group' => true,
                'name' => $conversation->name,
                'avatar' => $groupAvatar,
                'created_by' => $conversation->created_by,
                'buyer_id' => $conversation->buyer_id,
                'seller_id' => null,
                'can_complain' => false,
                'member_count' => $members->count(),
                'product' => null,
                'participants' => $members->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'avatar' => $member->displayAvatarPath(),
                    'online' => ChatService::isOnline($member),
                    'last_seen_at' => $member->last_seen_at?->toIso8601String(),
                ])->values(),
                'other' => [
                    'id' => null,
                    'name' => $conversation->name ?: 'Group',
                    'avatar' => $groupAvatar,
                    'online' => $onlineCount > 0,
                    'online_count' => $onlineCount,
                    'last_seen_at' => null,
                    'is_seller' => false,
                    'is_group' => true,
                    'member_count' => $members->count(),
                ],
                'latest_message' => $latest ? [
                    'body' => match ($latest->type) {
                        MessageType::Product => 'Product: '.($latest->body ?: ($latest->metadata['product']['name'] ?? 'Shared a product')),
                        MessageType::Transfer => ChatService::transferPreviewForMessage($latest, $user),
                        MessageType::System => $latest->body ?: 'Group update',
                        default => $latest->body,
                    },
                    'type' => $latest->type->value,
                    'created_at' => $latest->created_at?->toIso8601String(),
                    'sender_id' => $latest->sender_id,
                ] : null,
                'unread_count' => $unread,
                'last_message_at' => ($latest?->created_at ?? $conversation->last_message_at)?->toIso8601String(),
            ];
        }

        $other = $conversation->otherParticipant($user);
        $other->loadMissing('sellerProfile');

        $product = ChatService::sharedProductForConversation($conversation);
        $productPayload = $product ? ChatService::productCardPayload($product) : null;
        $canComplain = $conversation->buyer_id === $user->id
            && $conversation->seller_id !== $user->id;

        return [
            'id' => $conversation->id,
            'is_group' => false,
            'name' => null,
            'buyer_id' => $conversation->buyer_id,
            'seller_id' => $conversation->seller_id,
            'can_complain' => $canComplain,
            'product' => $productPayload,
            'other' => [
                'id' => $other->id,
                'name' => $other->name,
                'avatar' => $other->displayAvatarPath(),
                'online' => ChatService::isOnline($other),
                'last_seen_at' => $other->last_seen_at?->toIso8601String(),
                'city' => $other->city,
                'region' => $other->region,
                'is_seller' => $other->sellerProfile !== null || $other->isSeller(),
                'store_slug' => $other->sellerProfile?->slug,
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
    }
}
