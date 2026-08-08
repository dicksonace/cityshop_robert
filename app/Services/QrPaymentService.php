<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class QrPaymentService
{
    public const PREFIX = 'CS1';

    /**
     * Build a signed receive payload for the user's My QR screen.
     *
     * @return array{payload: string, user: array<string, mixed>, amount: float|null, expires_at: string}
     */
    public static function receiveCode(User $user, ?float $amount = null): array
    {
        $amount = $amount !== null ? round($amount, 2) : null;
        if ($amount !== null && ($amount < 1 || $amount > 50000)) {
            throw ValidationException::withMessages([
                'amount' => ['Amount must be between GH₵1 and GH₵50,000.'],
            ]);
        }

        $expiresAt = now()->addHours(24);
        $body = [
            'v' => 1,
            'u' => $user->id,
            'n' => $user->name,
            'e' => $expiresAt->getTimestamp(),
        ];
        if ($amount !== null) {
            $body['a'] = $amount;
        }

        $encoded = self::base64UrlEncode(json_encode($body, JSON_UNESCAPED_UNICODE));
        $sig = self::sign($encoded);
        $payload = self::PREFIX.'.'.$encoded.'.'.$sig;

        return [
            'payload' => $payload,
            'user' => self::publicUser($user),
            'amount' => $amount,
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * @return array{user: array<string, mixed>, amount: float|null, expires_at: string|null}
     */
    public static function resolvePayload(string $raw, ?User $payer = null): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            throw ValidationException::withMessages([
                'payload' => ['No QR code detected.'],
            ]);
        }

        if (preg_match('#(?:cityshop://pay|https?://[^/]+/pay)\?c=([A-Za-z0-9\-_\.]+)#i', $raw, $m)) {
            $raw = $m[1];
        }

        $parts = explode('.', $raw);
        if (count($parts) !== 3 || $parts[0] !== self::PREFIX) {
            throw ValidationException::withMessages([
                'payload' => ['This is not a CityShop payment QR code.'],
            ]);
        }

        [, $encoded, $sig] = $parts;

        if (! hash_equals(self::sign($encoded), $sig)) {
            throw ValidationException::withMessages([
                'payload' => ['This payment QR code is invalid or tampered with.'],
            ]);
        }

        $json = self::base64UrlDecode($encoded);
        $data = json_decode($json, true);
        if (! is_array($data) || empty($data['u'])) {
            throw ValidationException::withMessages([
                'payload' => ['This payment QR code is corrupted.'],
            ]);
        }

        if (! empty($data['e']) && (int) $data['e'] < time()) {
            throw ValidationException::withMessages([
                'payload' => ['This payment QR code has expired. Ask them to refresh My QR.'],
            ]);
        }

        $user = User::query()
            ->whereKey((int) $data['u'])
            ->where('role', '!=', UserRole::Admin)
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'payload' => ['No CityShop account found for this QR code.'],
            ]);
        }

        if ($payer && $payer->id === $user->id) {
            throw ValidationException::withMessages([
                'payload' => ['You cannot pay your own QR code.'],
            ]);
        }

        $amount = isset($data['a']) ? round((float) $data['a'], 2) : null;

        return [
            'user' => self::publicUser($user),
            'amount' => $amount,
            'expires_at' => ! empty($data['e'])
                ? \Illuminate\Support\Carbon::createFromTimestamp((int) $data['e'])->toIso8601String()
                : null,
        ];
    }

    /**
     * @return array{reference: string, amount: float, note: ?string, currency: string, recipient: array<string, mixed>}
     */
    public static function pay(User $payer, string $payload, float $amount, ?string $note = null): array
    {
        $resolved = self::resolvePayload($payload, $payer);
        $recipient = User::query()->findOrFail((int) $resolved['user']['id']);

        if ($resolved['amount'] !== null && abs($resolved['amount'] - $amount) > 0.001) {
            throw ValidationException::withMessages([
                'amount' => ['This QR code is for GH₵'.number_format($resolved['amount'], 2).'.'],
            ]);
        }

        $transfer = WalletService::transfer($payer, $recipient, $amount, $note ?: 'QR payment');

        $conversationId = null;
        try {
            $conversation = ChatService::findOrCreateConversation($payer, $recipient);
            $amountLabel = 'GH₵'.number_format($transfer['amount'], 2);
            $body = $transfer['note']
                ? "Transferred {$amountLabel} — {$transfer['note']}"
                : "Transferred {$amountLabel}";

            ChatService::sendMessage(
                $conversation,
                $payer,
                $body,
                MessageType::Transfer,
                [
                    'transfer' => [
                        'amount' => $transfer['amount'],
                        'currency' => 'GHS',
                        'note' => $transfer['note'],
                        'reference' => $transfer['reference'],
                        'from_user_id' => $payer->id,
                        'to_user_id' => $recipient->id,
                        'from_name' => $payer->name,
                        'to_name' => $recipient->name,
                        'via' => 'qr',
                    ],
                ],
            );
            $conversationId = $conversation->id;
        } catch (\Throwable $e) {
            // Wallet already moved; chat bubble is best-effort (e.g. blocked users).
            report($e);
        }

        // Always hit the notifications bell (even if chat was blocked).
        try {
            AppNotificationService::notifyQrPayment($payer, $recipient, $transfer, $conversationId);
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            ...$transfer,
            'recipient' => self::publicUser($recipient),
            'conversation_id' => $conversationId,
        ];
    }

    /**
     * @return array{id: int, name: string, mobile: ?string, role: ?string, avatar: ?string}
     */
    public static function publicUser(User $user): array
    {
        $avatar = $user->displayAvatarPath();
        $avatarUrl = null;
        if (is_string($avatar) && trim($avatar) !== '') {
            $avatarUrl = str_starts_with($avatar, 'http')
                ? $avatar
                : Storage::disk('public')->url($avatar);
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'role' => $user->role?->value,
            'avatar' => $avatarUrl,
        ];
    }

    private static function sign(string $encoded): string
    {
        return hash_hmac('sha256', $encoded, (string) config('app.key'));
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $remainder = strlen($value) % 4;
        if ($remainder > 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw ValidationException::withMessages([
                'payload' => ['This payment QR code is corrupted.'],
            ]);
        }

        return $decoded;
    }
}
