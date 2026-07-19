<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class PlatformSettings
{
    public const FUNDING_ACCOUNTS_KEY = 'manual_funding_accounts';

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("platform_setting.{$key}", 3600, function () use ($key, $default) {
            $setting = PlatformSetting::where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        PlatformSetting::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) || is_object($value) ? json_encode($value) : (string) $value],
        );

        Cache::forget("platform_setting.{$key}");
    }

    public static function commissionRate(): float
    {
        return (float) static::get('commission_rate', 0);
    }

    /**
     * @return array{
     *   enabled: bool,
     *   instructions: string,
     *   accounts: list<array<string, mixed>>
     * }
     */
    public static function manualFundingAccounts(): array
    {
        $raw = static::get(self::FUNDING_ACCOUNTS_KEY);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        if (! is_array($decoded)) {
            return [
                'enabled' => false,
                'instructions' => 'Send payment to one of the accounts below, then submit your proof and reference so we can credit your wallet.',
                'accounts' => [],
            ];
        }

        return [
            'enabled' => (bool) ($decoded['enabled'] ?? false),
            'instructions' => (string) ($decoded['instructions'] ?? ''),
            'accounts' => array_values($decoded['accounts'] ?? []),
        ];
    }

    /**
     * @param  array{enabled?: bool, instructions?: string, accounts?: list<array<string, mixed>>}  $data
     */
    public static function saveManualFundingAccounts(array $data): void
    {
        static::set(self::FUNDING_ACCOUNTS_KEY, [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'instructions' => (string) ($data['instructions'] ?? ''),
            'accounts' => array_values($data['accounts'] ?? []),
        ]);
    }
}
