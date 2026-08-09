<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use App\Services\ChatService;
use App\Services\PaymentPinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
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

        return response()->json(['data' => $conversations]);
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
            'buyer:id,name,avatar,city,region,last_seen_at',
            'seller:id,name,avatar,city,region,last_seen_at',
            'participants:id,name,avatar,last_seen_at',
            'latestVisibleMessage.sender:id,name',
        ]);

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $user, detailed: true),
            'messages' => $this->threadFor($conversation, $user),
        ], 201);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'seller_id' => ['nullable', 'exists:users,id'],
            'user_id' => ['nullable', 'exists:users,id'],
            'product_id' => ['nullable', 'exists:products,id'],
        ]);

        $peerId = $validated['user_id'] ?? $validated['seller_id'] ?? null;
        if ($peerId === null) {
            return response()->json(['message' => 'Choose someone to chat with.'], 422);
        }

        $peer = User::findOrFail($peerId);
        $product = isset($validated['product_id']) ? Product::find($validated['product_id']) : null;

        if ($product && (int) $product->seller_id !== (int) $peer->id) {
            return response()->json(['message' => 'That product does not belong to this seller.'], 422);
        }

        if ($request->user()->id === $peer->id) {
            return response()->json(['message' => 'You cannot message yourself.'], 422);
        }

        $conversation = ChatService::findOrCreateConversation($request->user(), $peer, $product);

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
            'attach_product' => $product ? ChatService::productCardPayload($product) : null,
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

        $pendingSignals = ChatService::pollCallSignals($conversation, 0)
            ->map(fn (Message $m) => ChatService::formatMessage($m, $request->user()))
            ->values();

        return response()->json([
            'conversation' => $this->formatConversation($conversation, $request->user(), detailed: true),
            'messages' => $this->threadFor($conversation, $request->user()),
            // SDP offers are not in the visible thread — surface them on open so
            // the callee can ring immediately after tapping the call push.
            'pending_call_signals' => $pendingSignals,
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ]);

        $replyTo = null;
        if (! empty($validated['reply_to_id'])) {
            $replyTo = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('id', $validated['reply_to_id'])
                ->whereIn('type', [
                    MessageType::Text,
                    MessageType::Image,
                    MessageType::Video,
                    MessageType::Voice,
                    MessageType::Product,
                    MessageType::Transfer,
                    MessageType::File,
                ])
                ->with('sender:id,name')
                ->first();

            if (! $replyTo) {
                return response()->json(['message' => 'That message can no longer be replied to.'], 422);
            }
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
        ], 201);
    }

    public function sendProduct(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if (! $conversation->is_group) {
            $sellerId = $conversation->seller_id;
            if ((int) $product->seller_id !== (int) $sellerId) {
                return response()->json(['message' => 'That product does not belong to this seller.'], 422);
            }
        }

        $message = ChatService::shareProductCard($conversation, $request->user(), $product, force: true);

        if (! $message) {
            return response()->json(['message' => 'Could not share product.'], 422);
        }

        $message->load('sender:id,name');

        return response()->json([
            'message' => ChatService::formatMessage($message, $request->user()),
            'conversation' => $this->formatConversation(
                $conversation->fresh([
                    'buyer:id,name,avatar,city,region,last_seen_at',
                    'seller:id,name,avatar,city,region,last_seen_at',
                    'seller.sellerProfile:id,user_id,business_name,store_name,slug,shop_photo',
                    'product:id,name,slug,price,discount_price',
                    'product.images',
                ]),
                $request->user(),
                detailed: true,
            ),
        ], 201);
    }

    public function sendTransfer(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);
        if ($conversation->is_group) {
            return response()->json(['message' => 'Wallet transfers are only available in 1:1 chats.'], 422);
        }

        $available = (float) \App\Services\WalletService::ensure($request->user())->available_balance;

        $validated = $request->validate(
            [
                'amount' => [
                    'required',
                    'numeric',
                    'min:1',
                    function (string $attribute, mixed $value, \Closure $fail) use ($available): void {
                        $amount = (float) $value;
                        if ($amount > $available + 0.0001) {
                            $fail(
                                'Insufficient balance. You have GH₵'.number_format($available, 2)
                                .' available.'
                            );

                            return;
                        }
                        if ($amount > 50000) {
                            $fail('Maximum transfer is GH₵50,000.00 per send.');
                        }
                    },
                ],
                'note' => ['nullable', 'string', 'max:120'],
                'payment_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            ],
            [
                'amount.min' => 'Minimum transfer is GH₵1.00.',
                'payment_pin.required' => 'Enter your 4-digit payment PIN.',
                'payment_pin.regex' => 'Payment PIN must be 4 digits.',
            ],
        );

        PaymentPinService::assertValidForAction($request->user(), $validated['payment_pin']);

        $conversation->loadMissing(['buyer', 'seller']);
        $recipient = $conversation->otherParticipant($request->user());

        try {
            $transfer = \App\Services\WalletService::transfer(
                $request->user(),
                $recipient,
                (float) $validated['amount'],
                $validated['note'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $amountLabel = 'GH₵'.number_format($transfer['amount'], 2);
        $body = $transfer['note']
            ? "Transferred {$amountLabel} — {$transfer['note']}"
            : "Transferred {$amountLabel}";

        $message = ChatService::sendMessage(
            $conversation,
            $request->user(),
            $body,
            MessageType::Transfer,
            [
                'transfer' => [
                    'amount' => $transfer['amount'],
                    'currency' => 'GHS',
                    'note' => $transfer['note'],
                    'reference' => $transfer['reference'],
                    'from_user_id' => $request->user()->id,
                    'to_user_id' => $recipient->id,
                    'from_name' => $request->user()->name,
                    'to_name' => $recipient->name,
                ],
            ],
        );

        $message->load('sender:id,name');

        return response()->json([
            'message' => ChatService::formatMessage($message, $request->user()),
            'wallet' => [
                'available_balance' => (float) (\App\Services\WalletService::ensure($request->user())->fresh()->available_balance),
            ],
        ], 201);
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
        ], 201);
    }

    public function uploadVideo(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $validated = $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm,video/3gpp', 'max:51200'],
            'caption' => ['nullable', 'string', 'max:500'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:600'],
        ]);

        $path = $request->file('video')->store('chat/'.$conversation->id, 'public');
        $url = Storage::disk('public')->url($path);

        $message = ChatService::sendMessage(
            $conversation,
            $request->user(),
            $validated['caption'] ?? '',
            MessageType::Video,
            [
                'video_path' => $path,
                'video_url' => $url,
                'duration_seconds' => $validated['duration_seconds'] ?? null,
            ],
        );

        $message->load('sender:id,name');

        return response()->json([
            'message' => ChatService::formatMessage($message, $request->user()),
        ], 201);
    }

    public function uploadVoice(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $validated = $request->validate([
            'voice' => [
                'required',
                'file',
                'max:10240',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        $fail('The voice field must be a file.');

                        return;
                    }

                    $mime = strtolower((string) $value->getMimeType());
                    $ext = strtolower((string) ($value->getClientOriginalExtension()
                        ?: pathinfo($value->getClientOriginalName(), PATHINFO_EXTENSION)));

                    // Android/MediaRecorder m4a is often sniffed as video/mp4 (same container).
                    $allowedMimes = [
                        'audio/mpeg',
                        'audio/mp4',
                        'audio/x-m4a',
                        'audio/m4a',
                        'audio/aac',
                        'audio/x-aac',
                        'audio/mp4a-latm',
                        'audio/wav',
                        'audio/x-wav',
                        'audio/webm',
                        'audio/ogg',
                        'audio/3gpp',
                        'audio/3gpp2',
                        'video/mp4',
                        'video/3gpp',
                        'application/mp4',
                        'application/octet-stream',
                    ];
                    $allowedExt = ['mp3', 'm4a', 'aac', 'wav', 'webm', 'ogg', '3gp', 'mpeg', 'mp4'];

                    if (! in_array($mime, $allowedMimes, true) && ! in_array($ext, $allowedExt, true)) {
                        $fail('The voice must be an audio file (m4a, mp3, wav, aac, ogg, or webm).');
                    }
                },
            ],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:600'],
        ]);

        $path = $request->file('voice')->store('chat/'.$conversation->id, 'public');
        $url = Storage::disk('public')->url($path);

        $message = ChatService::sendMessage(
            $conversation,
            $request->user(),
            '',
            MessageType::Voice,
            [
                'voice_path' => $path,
                'voice_url' => $url,
                'duration_seconds' => $validated['duration_seconds'] ?? null,
            ],
        );

        $message->load('sender:id,name');

        return response()->json([
            'message' => ChatService::formatMessage($message, $request->user()),
        ], 201);
    }

    public function uploadFile(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,rar,rtf,odt,ods',
            ],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $uploaded = $request->file('file');
        $path = $uploaded->store('chat/'.$conversation->id.'/files', 'public');
        $url = Storage::disk('public')->url($path);
        $originalName = $uploaded->getClientOriginalName() ?: 'file';
        $body = $validated['caption'] ?? $originalName;

        $message = ChatService::sendMessage(
            $conversation,
            $request->user(),
            $body,
            MessageType::File,
            [
                'file_path' => $path,
                'file_url' => $url,
                'file_name' => $originalName,
                'file_size' => $uploaded->getSize() ?: null,
                'file_mime' => $uploaded->getMimeType() ?: null,
            ],
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

        $readMessageIds = ChatService::recentReadMessageIds($conversation, $request->user());

        return response()->json([
            'messages' => $messages,
            'read_message_ids' => $readMessageIds,
        ]);
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

    public function destroyConversation(Request $request, Conversation $conversation): JsonResponse
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
            ->map(fn (Message $m) => ChatService::formatMessage($m, $request->user()))
            ->values();

        return response()->json(['messages' => $messages]);
    }

    public function signal(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);
        if ($conversation->is_group) {
            return response()->json(['message' => 'Voice calls are only available in 1:1 chats.'], 422);
        }

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

        $callLogMessage = null;
        if ($type === MessageType::CallEnd && ! empty($validated['metadata']['call_log'])) {
            $log = $validated['metadata']['call_log'];
            $callLogMessage = ChatService::recordCallLog(
                $conversation,
                $request->user(),
                $log['status'] ?? 'cancelled',
                (int) ($log['caller_id'] ?? $request->user()->id),
                (string) ($log['caller_name'] ?? $request->user()->name),
                (int) ($log['duration_seconds'] ?? 0),
                (string) ($log['call_kind'] ?? $validated['metadata']['call_kind'] ?? 'voice'),
            );
        }

        return response()->json([
            'ok' => true,
            'message_id' => $message->id,
            'call_log' => $callLogMessage
                ? ChatService::formatMessage($callLogMessage->load('sender:id,name'), $request->user())
                : null,
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
        $latest = $conversation->latestVisibleMessage;
        $unread = $conversation->messages()
            ->whereIn('type', ChatService::visibleTypes())
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();

        if ($conversation->is_group) {
            $conversation->loadMissing('participants:id,name,avatar,last_seen_at');
            $members = $conversation->participants;
            $memberCount = $members->count();

            return [
                'id' => $conversation->id,
                'is_group' => true,
                'name' => $conversation->name,
                'created_by' => $conversation->created_by,
                'buyer_id' => $conversation->buyer_id,
                'seller_id' => null,
                'can_complain' => false,
                'blocked' => false,
                'i_blocked' => false,
                'product' => null,
                'member_count' => $memberCount,
                'participants' => $members->map(fn (User $member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'avatar' => $this->publicMediaUrl($member->displayAvatarPath()),
                    'online' => ChatService::isOnline($member),
                ])->values(),
                'other' => [
                    'id' => null,
                    'name' => $conversation->name ?: 'Group',
                    'avatar' => null,
                    'online' => false,
                    'city' => null,
                    'region' => null,
                    'mobile' => null,
                    'store_name' => null,
                    'store_slug' => null,
                    'is_seller' => false,
                    'is_group' => true,
                    'member_count' => $memberCount,
                ],
                'latest_message' => $latest ? [
                    'body' => match ($latest->type) {
                        MessageType::Product => 'Product: '.($latest->body ?: ($latest->metadata['product']['name'] ?? 'Shared a product')),
                        MessageType::Transfer => ChatService::transferPreviewForMessage($latest, $user),
                        MessageType::File => $latest->body ?: ($latest->metadata['file_name'] ?? 'File'),
                        MessageType::Image => $latest->body ?: 'Photo',
                        MessageType::Video => $latest->body ?: 'Video',
                        MessageType::Voice => 'Voice message',
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
        $iBlocked = \App\Services\UserBlockService::iBlocked($user, $other);
        $blockedEitherWay = \App\Services\UserBlockService::isBlockedEitherWay($user, $other);
        $canComplain = $conversation->buyer_id === $user->id
            && $conversation->seller_id !== $user->id;

        return [
            'id' => $conversation->id,
            'is_group' => false,
            'name' => null,
            'buyer_id' => $conversation->buyer_id,
            'seller_id' => $conversation->seller_id,
            'can_complain' => $canComplain,
            'blocked' => $blockedEitherWay,
            'i_blocked' => $iBlocked,
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
                'store_slug' => $other->sellerProfile?->slug,
                'is_seller' => $other->sellerProfile !== null || $other->isSeller(),
            ],
            'latest_message' => $latest ? [
                'body' => match ($latest->type) {
                    MessageType::Product => 'Product: '.($latest->body ?: ($latest->metadata['product']['name'] ?? 'Shared a product')),
                    MessageType::Transfer => ChatService::transferPreviewForMessage($latest, $user),
                    MessageType::File => $latest->body ?: ($latest->metadata['file_name'] ?? 'File'),
                    MessageType::Image => $latest->body ?: 'Photo',
                    MessageType::Video => $latest->body ?: 'Video',
                    MessageType::Voice => 'Voice message',
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
