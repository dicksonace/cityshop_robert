<?php

namespace App\Support;

/**
 * ICE servers handed to the mobile app and the web chat so both peers agree on
 * how to traverse NAT. Without a TURN relay, calls between two devices on
 * mobile data fail after signalling succeeds because neither side can reach the
 * other's candidates.
 */
class IceServers
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function forClient(): array
    {
        $servers = [];

        $stun = self::urls(config('services.webrtc.stun_urls'));
        if ($stun !== []) {
            $servers[] = ['urls' => $stun];
        }

        $turn = self::urls(config('services.webrtc.turn_urls'));
        $username = (string) config('services.webrtc.turn_username', '');
        $password = (string) config('services.webrtc.turn_password', '');

        if ($turn !== [] && $username !== '' && $password !== '') {
            $servers[] = [
                'urls' => $turn,
                'username' => $username,
                'credential' => $password,
            ];
        }

        if ($servers === []) {
            $servers[] = ['urls' => ['stun:stun.l.google.com:19302']];
        }

        return $servers;
    }

    public static function hasRelay(): bool
    {
        return self::urls(config('services.webrtc.turn_urls')) !== []
            && filled(config('services.webrtc.turn_username'))
            && filled(config('services.webrtc.turn_password'));
    }

    /**
     * @return list<string>
     */
    private static function urls(mixed $raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $url) => $url !== '',
        ));
    }
}
