<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    /**
     * Send an OS push for an in-app notification when FCM is configured.
     */
    public static function sendForNotification(AppNotification $notification): void
    {
        $user = $notification->relationLoaded('user')
            ? $notification->user
            : User::query()->find($notification->user_id);

        if (! $user) {
            return;
        }

        static::sendToUser(
            $user,
            $notification->title,
            $notification->body,
            array_filter([
                'notification_id' => (string) $notification->id,
                'type' => (string) $notification->type,
                'order_id' => isset($notification->data['order_id'])
                    ? (string) $notification->data['order_id']
                    : null,
                'conversation_id' => isset($notification->data['conversation_id'])
                    ? (string) $notification->data['conversation_id']
                    : null,
            ], fn ($value) => $value !== null && $value !== ''),
        );
    }

    /**
     * @param  array<string, string>  $data
     */
    public static function sendToUser(User $user, string $title, ?string $body = null, array $data = []): void
    {
        $serverKey = config('services.fcm.server_key');
        if (! is_string($serverKey) || trim($serverKey) === '') {
            return;
        }

        $tokens = DeviceToken::query()
            ->where('user_id', $user->id)
            ->pluck('token')
            ->filter()
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return;
        }

        foreach ($tokens as $token) {
            static::sendToToken($serverKey, (string) $token, $title, $body, $data);
        }
    }

    /**
     * @param  array<string, string>  $data
     */
    private static function sendToToken(
        string $serverKey,
        string $token,
        string $title,
        ?string $body,
        array $data,
    ): void {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'Authorization' => 'key='.$serverKey,
                ])
                ->acceptJson()
                ->post('https://fcm.googleapis.com/fcm/send', [
                    'to' => $token,
                    'priority' => 'high',
                    'content_available' => true,
                    'notification' => [
                        'title' => $title,
                        'body' => $body ?: '',
                        'sound' => 'default',
                        'android_channel_id' => 'cityshop_alerts',
                    ],
                    'android' => [
                        'priority' => 'high',
                        'notification' => [
                            'channel_id' => 'cityshop_alerts',
                            'sound' => 'default',
                            'default_vibrate_timings' => true,
                        ],
                    ],
                    'data' => array_merge($data, [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    ]),
                ]);

            if ($response->failed()) {
                $error = $response->json('error') ?? $response->body();
                Log::warning('FCM push failed', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                // Drop invalid / unregistered tokens so we stop retrying them.
                $shouldForget = is_string($error) && (
                    str_contains(strtolower($error), 'notregistered')
                    || str_contains(strtolower($error), 'invalidregistration')
                );

                if ($shouldForget || in_array($response->status(), [400, 404], true)) {
                    DeviceToken::query()->where('token', $token)->delete();
                }

                return;
            }

            DeviceToken::query()
                ->where('token', $token)
                ->update(['last_used_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('FCM push exception: '.$e->getMessage());
        }
    }
}
