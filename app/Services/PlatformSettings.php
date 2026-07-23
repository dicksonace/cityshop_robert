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
        $decoded = is_array($raw)
            ? $raw
            : (is_string($raw) ? json_decode($raw, true) : null);

        if (! is_array($decoded)) {
            return [
                'enabled' => false,
                'instructions' => 'Send payment to one of the accounts below, then submit your proof and reference so we can credit your wallet.',
                'accounts' => [],
            ];
        }

        $accounts = array_values(array_map(function ($account) {
            if (! is_array($account)) {
                return null;
            }

            $type = ($account['type'] ?? '') === 'bank' ? 'bank' : 'momo';
            $accountNumber = (string) ($account['account_number'] ?? '');
            $accountName = (string) ($account['account_name'] ?? '');

            // CityShop receive numbers should always show business + Robert Asare.
            $canonical = static::cityShopReceiveAccountName($accountNumber);
            if ($canonical !== null) {
                $accountName = $canonical;
            }

            return [
                'type' => $type,
                'label' => (string) ($account['label'] ?? ''),
                'account_name' => $accountName,
                'account_number' => $accountNumber,
                'network' => $type === 'momo'
                    ? (static::normalizeMomoNetwork($account['network'] ?? null) ?? 'mtn')
                    : null,
                'bank_name' => $type === 'bank' ? ($account['bank_name'] ?? null) : null,
            ];
        }, $decoded['accounts'] ?? []));

        $accounts = array_values(array_filter($accounts));
        $accounts = static::ensureCityShopMomoAccounts($accounts);

        return [
            'enabled' => (bool) ($decoded['enabled'] ?? false),
            'instructions' => (string) ($decoded['instructions'] ?? ''),
            'accounts' => $accounts,
        ];
    }

    /**
     * Canonical MoMo account name for CityShop’s public receive numbers.
     */
    public static function cityShopReceiveAccountName(string $accountNumber): ?string
    {
        $digits = preg_replace('/\D+/', '', $accountNumber) ?? '';

        return match ($digits) {
            '0539790093', '513014', '0273706541' => 'City Unlock Ventures / Robert Asare',
            default => null,
        };
    }

    /**
     * MTN / Telecel / AirtelTigo receive accounts used for manual deposits.
     *
     * @return list<array<string, mixed>>
     */
    public static function defaultCityShopMomoAccounts(): array
    {
        return [
            [
                'type' => 'momo',
                'label' => 'MTN Mobile Money',
                'account_name' => 'City Unlock Ventures / Robert Asare',
                'account_number' => '0539790093',
                'network' => 'mtn',
                'bank_name' => null,
            ],
            [
                'type' => 'momo',
                'label' => 'Telecel Cash',
                'account_name' => 'City Unlock Ventures / Robert Asare',
                'account_number' => '513014',
                'network' => 'telecel',
                'bank_name' => null,
            ],
            [
                'type' => 'momo',
                'label' => 'AirtelTigo Cash',
                'account_name' => 'City Unlock Ventures / Robert Asare',
                'account_number' => '0273706541',
                'network' => 'airteltigo',
                'bank_name' => null,
            ],
        ];
    }

    /**
     * Fill in any missing CityShop MoMo network so buyers never see “Not configured”.
     *
     * @param  list<array<string, mixed>>  $accounts
     * @return list<array<string, mixed>>
     */
    public static function ensureCityShopMomoAccounts(array $accounts): array
    {
        $byNetwork = [];
        foreach ($accounts as $account) {
            if (($account['type'] ?? '') !== 'momo') {
                continue;
            }
            $network = static::normalizeMomoNetwork($account['network'] ?? null);
            if ($network) {
                $byNetwork[$network] = true;
            }
        }

        foreach (static::defaultCityShopMomoAccounts() as $default) {
            $network = $default['network'];
            if (! isset($byNetwork[$network])) {
                $accounts[] = $default;
                $byNetwork[$network] = true;
            }
        }

        return array_values($accounts);
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

    /**
     * Normalize free-text / legacy network labels to canonical ids: mtn|telecel|airteltigo.
     */
    public static function normalizeMomoNetwork(?string $network): ?string
    {
        if ($network === null || trim($network) === '') {
            return null;
        }

        $raw = mb_strtolower(trim($network));
        $compact = str_replace([' ', '-', '_'], '', $raw);

        if (in_array($compact, ['mtn', 'telecel', 'airteltigo'], true)) {
            return $compact;
        }

        return match (true) {
            str_contains($compact, 'mtn') => 'mtn',
            str_contains($compact, 'telecel'), str_contains($compact, 'vodafone') => 'telecel',
            str_contains($compact, 'airtel'), str_contains($compact, 'tigo') => 'airteltigo',
            default => null,
        };
    }
}
