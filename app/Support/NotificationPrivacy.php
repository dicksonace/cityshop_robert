<?php

namespace App\Support;

class NotificationPrivacy
{
    public static function money(float $amount): string
    {
        return 'GH₵'.number_format($amount, 2);
    }

    public static function maskEmail(?string $email): string
    {
        $email = strtolower(trim((string) $email));
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];
        $visible = max(1, (int) floor(strlen($local) / 3));

        return substr($local, 0, $visible).str_repeat('*', max(3, strlen($local) - $visible)).'@'.$domain;
    }

    public static function maskAccount(?string $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        $len = strlen($digits);

        if ($len < 6) {
            return 'your account';
        }

        return substr($digits, 0, 3).str_repeat('*', min($len - 6, 6)).substr($digits, -3);
    }

    public static function fundingMethod(string $method): string
    {
        return match (strtolower(trim($method))) {
            'paystack', 'card' => 'Paystack',
            'momo', 'mobile_money', 'mtn', 'telecel', 'airteltigo' => 'MoMo',
            'manual' => 'manual top-up',
            'admin' => 'CityShop credit',
            default => $method !== '' ? $method : 'wallet',
        };
    }
}
