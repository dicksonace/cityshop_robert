<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
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
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Conversation $c) => $this->formatConversation($c, $request->user()));

        return response()->json(['data' => $conversations]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seller_id' => ['required', 'exists:users,id'],
            'product_id' => ['nullable', 'exists:products,id'],
        ]);

        $seller = User::findOrFail($validated['seller_id']);
        $product = isset($validated['product_id']) ? Product::find($validated['product_id']) : null;

        if ($request->user()->id === $seller->id) {
            return response()->json(['message' => 'You cannot message yourself.'], 422);
        }

        $conversation = ChatService::findOrCreateConversation($request->user(), $seller, $product);

        $conversation->load([
            'buyer:id,name,avatar,city,region,last_seen_at',
            'seller:id,name,avatar,city,region,last_seen_at',
            'seller.sellerProfile:id,user_id,business_name,store_name,slug,business_address,shop_photo',
            'product:id,name,slug,price,discount_price',
            'product.images',
        ]);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $request->user(), detailed: true),
            'messages' => $this->threadFor($conversation, $request->user()),
        ], 201);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
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

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $request->user(), detailed: true),
            'messages' => $this->threadFor($conversation, $request->user()),
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = ChatService::sendMessage(
            $conversation,
            $request->user(),
            $validated['body'],
            MessageType::Text,
        );

        $message->load('sender:id,name');

        return response()->json([
            'message' => ChatService::formatMessage($message, $request->user()),
        ], 201);
    }

    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $afterId = (int) $request->get('after', 0);

        $messages = $conversation->messages()
            ->whereIn('type', ChatService::visibleTypes())
            ->with('sender:id,name')
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('created_at')
            ->get()
            ->map(fn ($m) => ChatService::formatMessage($m, $request->user()));

        if ($messages->isNotEmpty()) {
            ChatService::markConversationRead($conversation, $request->user());
        }

        return response()->json(['messages' => $messages]);
    }

    public function destroy(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);
        abort_unless($message->conversation_id === $conversation->id, 404);

        try {
            $message = ChatService::deleteMessage($message, $request->user());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json([
            'message' => ChatService::formatMessage($message, $request->user()),
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function threadFor(Conversation $conversation, User $user)
    {
        return $conversation->messages()
            ->whereIn('type', ChatService::visibleTypes())
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->map(fn (Message $m) => ChatService::formatMessage($m, $user));
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
        $product = $conversation->product;

        return [
            'id' => $conversation->id,
            'product' => $product ? [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'price' => $product->effectivePrice(),
                'image_url' => $this->publicMediaUrl($this->productImagePath($product)),
            ] : null,
            'other' => [
                'id' => $other->id,
                'name' => $other->name,
                'avatar' => $this->publicMediaUrl($other->displayAvatarPath()),
                'online' => ChatService::isOnline($other),
                'city' => $other->city,
                'region' => $other->region,
                'mobile' => $other->mobile,
                'store_name' => $other->sellerProfile?->displayName(),
            ],
            'latest_message' => $latest ? [
                'body' => $latest->body,
                'type' => $latest->type->value,
                'created_at' => $latest->created_at?->toIso8601String(),
                'sender_id' => $latest->sender_id,
            ] : null,
            'unread_count' => $unread,
            'last_message_at' => ($latest?->created_at ?? $conversation->last_message_at)?->toIso8601String(),
        ];
    }

    private function productImagePath(Product $product): ?string
    {
        $product->loadMissing('images');

        return ($product->images->firstWhere('is_primary', true) ?? $product->images->first())?->path;
    }

    private function publicMediaUrl(?string $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
