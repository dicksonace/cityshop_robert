<?php

namespace App\Http\Controllers\Chat;

use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Product;
use App\Services\ChatService;
use App\Services\PaymentPinService;
use App\Services\WalletService;
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
                ->whereIn('type', [
                    MessageType::Text,
                    MessageType::Image,
                    MessageType::Video,
                    MessageType::Voice,
                    MessageType::Product,
                ])
                ->with('sender:id,name')
                ->first();

            if (! $replyTo) {
                return response()->json([
                    'message' => 'You can only reply to messages in this conversation.',
                ], 422);
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
        ]);
    }

    public function sendProduct(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
        ]);

        $product = Product::findOrFail($validated['product_id']);

        if ((int) $product->seller_id !== (int) $conversation->seller_id) {
            return response()->json(['message' => 'That product does not belong to this seller.'], 422);
        }

        $message = ChatService::shareProductCard($conversation, $request->user(), $product, force: true);

        if (! $message) {
            return response()->json(['message' => 'Could not share product.'], 422);
        }

        $message->load('sender:id,name');

        return response()->json([
            'message' => ChatService::formatMessage($message, $request->user()),
        ]);
    }

    public function transferMeta(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        if ($conversation->is_group) {
            return response()->json(['message' => 'Wallet transfers are only available in 1:1 chats.'], 422);
        }

        $conversation->loadMissing(['buyer', 'seller']);
        $recipient = $conversation->otherParticipant($request->user());
        $wallet = WalletService::ensure($request->user());

        return response()->json([
            'available_balance' => (float) $wallet->available_balance,
            'has_payment_pin' => PaymentPinService::hasPin($request->user()),
            'recipient' => [
                'id' => $recipient->id,
                'name' => $recipient->name,
                'mobile' => $recipient->mobile,
                'avatar' => $recipient->avatar,
            ],
        ]);
    }

    public function sendTransfer(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);

        if ($conversation->is_group) {
            return response()->json(['message' => 'Wallet transfers are only available in 1:1 chats.'], 422);
        }

        $available = (float) WalletService::ensure($request->user())->available_balance;

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
            $transfer = WalletService::transfer(
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
                'available_balance' => (float) (WalletService::ensure($request->user())->fresh()->available_balance),
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
        ]);
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
        ]);
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
        ]);
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

    public function react(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);
        abort_unless($message->conversation_id === $conversation->id, 404);

        $validated = $request->validate([
            'emoji' => ['required', 'string', 'max:64'],
        ]);

        $message = ChatService::reactToMessage($message, $request->user(), $validated['emoji']);

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

    public function forward(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);
        abort_unless($message->conversation_id === $conversation->id, 404);

        $validated = $request->validate([
            'member_ids' => ['required', 'array', 'min:1', 'max:49'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        try {
            $result = ChatService::forwardToMembers(
                $conversation,
                $message,
                $request->user(),
                $validated['member_ids'],
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
        }

        return response()->json([
            'sent' => $result['sent'],
            'message' => $result['sent'] === 1
                ? 'Forwarded to 1 member.'
                : 'Forwarded to '.$result['sent'].' members.',
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
}
