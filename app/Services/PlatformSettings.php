<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class PlatformSettings
{
    public const FUNDING_ACCOUNTS_KEY = 'manual_funding_accounts';

    public const WITHDRAWAL_FEE_KEY = 'withdrawal_fee';

    public const AUTO_PAYSTACK_WITHDRAW_KEY = 'auto_paystack_withdraw';

    public const PAYSTACK_FEE_KEY = 'paystack_collection_fee';

    /** When locked, buyers cannot start Paystack checkout or wallet top-up. */
    public const PAYSTACK_PAYMENTS_KEY = 'paystack_payments';

    /** When locked, buyers cannot start Flutterwave checkout or wallet top-up. */
    public const FLUTTERWAVE_PAYMENTS_KEY = 'flutterwave_payments';

    public const SMS_KEY = 'sms_provider';

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("platform_setting.{$key}", 3600, function () use ($key, $default) {
            $setting = PlatformSetting::where('key', $key)->first();
            $value = $setting?->value ?? $default;

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                    $decoded = json_decode($trimmed, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $decoded;
                    }
                }
            }

            return $value;
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
     * Flat / tiered withdrawal fee (per transaction). Admin can change amount / which channels.
     *
     * @return array{
     *   enabled: bool,
     *   amount: float,
     *   momo_amount: float,
     *   applies_to: string,
     *   bank_tiers: list<array{min: float, max: float|null, fee: float}>
     * }
     */
    public static function withdrawalFeeSettings(): array
    {
        $raw = static::get(self::WITHDRAWAL_FEE_KEY);
        $decoded = is_array($raw)
            ? $raw
            : (is_string($raw) ? json_decode($raw, true) : null);

        if (! is_array($decoded)) {
            return [
                'enabled' => true,
                'amount' => 10.0,
                'momo_amount' => 0.0,
                'applies_to' => 'bank',
                'bank_tiers' => static::defaultBankFeeTiers(),
            ];
        }

        $appliesTo = (string) ($decoded['applies_to'] ?? 'bank');
        if (! in_array($appliesTo, ['bank', 'momo', 'all', 'none'], true)) {
            $appliesTo = 'bank';
        }

        $amount = max(0, round((float) ($decoded['amount'] ?? 10), 2));
        $bankTiers = static::normalizeBankFeeTiers($decoded['bank_tiers'] ?? null);
        $enabled = (bool) ($decoded['enabled'] ?? true);

        // Admins often fill bank bands + "Charge bank fee bands" but leave the master
        // toggle off — sellers then see "No fee". Treat configured bank bands as on.
        if (! $enabled && in_array($appliesTo, ['bank', 'all'], true) && static::bankTiersHaveFees($bankTiers)) {
            $enabled = true;
        }

        return [
            'enabled' => $enabled,
            'amount' => $amount,
            'momo_amount' => static::resolveMomoFeeAmount($decoded, $appliesTo, $amount),
            'applies_to' => $appliesTo,
            'bank_tiers' => $bankTiers,
        ];
    }

    /**
     * @param  list<array{min: float, max: float|null, fee: float}>  $tiers
     */
    private static function bankTiersHaveFees(array $tiers): bool
    {
        foreach ($tiers as $tier) {
            if ((float) ($tier['fee'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * MoMo fee is 0 unless admin sets it. Legacy rows that applied the flat
     * `amount` to MoMo (applies_to momo/all) keep that amount until saved again.
     *
     * @param  array<string, mixed>  $decoded
     */
    private static function resolveMomoFeeAmount(array $decoded, string $appliesTo, float $amount): float
    {
        if (array_key_exists('momo_amount', $decoded) && $decoded['momo_amount'] !== null && $decoded['momo_amount'] !== '') {
            return max(0, round((float) $decoded['momo_amount'], 2));
        }

        if (in_array($appliesTo, ['momo', 'all'], true)) {
            return $amount;
        }

        return 0.0;
    }

    /**
     * Default CityShop bank withdrawal fee schedule.
     *
     * @return list<array{min: float, max: float|null, fee: float}>
     */
    public static function defaultBankFeeTiers(): array
    {
        return [
            ['min' => 10.0, 'max' => 999.99, 'fee' => 10.0],
            ['min' => 1000.0, 'max' => 25000.0, 'fee' => 20.0],
        ];
    }

    /**
     * @param  mixed  $raw
     * @return list<array{min: float, max: float|null, fee: float}>
     */
    public static function normalizeBankFeeTiers(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            return static::defaultBankFeeTiers();
        }

        $tiers = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $min = max(0, round((float) ($row['min'] ?? 0), 2));
            $fee = max(0, round((float) ($row['fee'] ?? 0), 2));
            $maxRaw = $row['max'] ?? null;
            $max = $maxRaw === null || $maxRaw === '' ? null : max($min, round((float) $maxRaw, 2));
            $tiers[] = ['min' => $min, 'max' => $max, 'fee' => $fee];
        }

        if ($tiers === []) {
            return static::defaultBankFeeTiers();
        }

        usort($tiers, fn ($a, $b) => $a['min'] <=> $b['min']);

        return static::ensureTwentyFromThousand(array_values($tiers));
    }

    /**
     * CityShop bank rule: GH₵10 below GH₵1,000, GH₵20 from GH₵1,000.
     * Old admin rows often kept a single GH₵10 band (or first band max 1000),
     * so GH₵1,500 still charged GH₵10.
     *
     * @param  list<array{min: float, max: float|null, fee: float}>  $tiers
     * @return list<array{min: float, max: float|null, fee: float}>
     */
    private static function ensureTwentyFromThousand(array $tiers): array
    {
        if ($tiers === []) {
            return static::defaultBankFeeTiers();
        }

        $onlyDefaultFees = true;
        foreach ($tiers as $tier) {
            $fee = (float) $tier['fee'];
            if ($fee !== 10.0 && $fee !== 20.0) {
                $onlyDefaultFees = false;
                break;
            }
        }

        if (! $onlyDefaultFees) {
            return $tiers;
        }

        $feeAt999 = static::feeFromBankTiers(999, $tiers, 10);
        $feeAt1000 = static::feeFromBankTiers(1000, $tiers, 10);
        $feeAt1500 = static::feeFromBankTiers(1500, $tiers, 10);

        if ($feeAt999 === 10.0 && $feeAt1000 >= 20.0 && $feeAt1500 >= 20.0) {
            return $tiers;
        }

        return static::defaultBankFeeTiers();
    }

    /**
     * Resolve fee from bank amount bands.
     * Between bands → next (higher) fee; above last band → last fee.
     *
     * @param  list<array{min: float, max: float|null, fee: float}>  $tiers
     */
    public static function feeFromBankTiers(float $amount, array $tiers, float $fallback = 0.0): float
    {
        if ($amount <= 0 || $tiers === []) {
            return max(0, $fallback);
        }

        foreach ($tiers as $tier) {
            $min = (float) $tier['min'];
            $max = $tier['max'];
            if ($amount + 0.0001 >= $min && ($max === null || $amount <= ((float) $max) + 0.0001)) {
                return (float) $tier['fee'];
            }
        }

        // Below first band → first fee.
        if ($amount < (float) $tiers[0]['min']) {
            return (float) $tiers[0]['fee'];
        }

        // Between bands → next band fee (e.g. ₵5,000 uses the GH₵20 band, not GH₵10).
        for ($i = 0; $i < count($tiers) - 1; $i++) {
            $currMax = $tiers[$i]['max'];
            $nextMin = (float) $tiers[$i + 1]['min'];
            if ($currMax !== null && $amount > (float) $currMax && $amount < $nextMin) {
                return (float) $tiers[$i + 1]['fee'];
            }
        }

        // Above last band → last fee.
        return (float) $tiers[array_key_last($tiers)]['fee'];
    }

    /**
     * @param  array{
     *   enabled?: bool,
     *   amount?: float|int|string,
     *   momo_amount?: float|int|string,
     *   applies_to?: string,
     *   bank_tiers?: list<array{min?: float|int|string, max?: float|int|string|null, fee?: float|int|string}>
     * }  $data
     */
    public static function saveWithdrawalFeeSettings(array $data): void
    {
        $appliesTo = (string) ($data['applies_to'] ?? 'bank');
        if (! in_array($appliesTo, ['bank', 'momo', 'all', 'none'], true)) {
            $appliesTo = 'bank';
        }

        $bankTiers = static::normalizeBankFeeTiers($data['bank_tiers'] ?? null);
        $enabled = (bool) ($data['enabled'] ?? false);
        // Selecting "Charge bank fee bands" with real fees always turns bank fees on.
        if (in_array($appliesTo, ['bank', 'all'], true) && static::bankTiersHaveFees($bankTiers)) {
            $enabled = true;
        }
        if ($appliesTo === 'none') {
            $enabled = false;
        }

        static::set(self::WITHDRAWAL_FEE_KEY, [
            'enabled' => $enabled,
            'amount' => max(0, round((float) ($data['amount'] ?? 0), 2)),
            'momo_amount' => max(0, round((float) ($data['momo_amount'] ?? 0), 2)),
            'applies_to' => $appliesTo,
            'bank_tiers' => $bankTiers,
        ]);
    }

    /**
     * Paystack auto-payout (no admin approval). Fee is flat (like bank) or percent.
     *
     * @return array{enabled: bool, fee_mode: string, fee_flat: float, fee_percent: float}
     */
    public static function autoPaystackWithdrawSettings(): array
    {
        $raw = static::get(self::AUTO_PAYSTACK_WITHDRAW_KEY);
        $decoded = is_array($raw)
            ? $raw
            : (is_string($raw) ? json_decode($raw, true) : null);

        if (! is_array($decoded)) {
            return [
                'enabled' => false,
                'fee_mode' => 'flat',
                'fee_flat' => 1.0,
                'fee_percent' => 0.0,
            ];
        }

        $feePercent = max(0, min(25, round((float) ($decoded['fee_percent'] ?? 0), 2)));
        $feeFlat = max(0, min(500, round((float) ($decoded['fee_flat'] ?? 0), 2)));
        $mode = (string) ($decoded['fee_mode'] ?? '');
        if (! in_array($mode, ['flat', 'percent'], true)) {
            // Legacy rows only stored percent — keep percent behaviour if it was set.
            $mode = $feePercent > 0 ? 'percent' : 'flat';
        }

        return [
            'enabled' => (bool) ($decoded['enabled'] ?? false),
            'fee_mode' => $mode,
            'fee_flat' => $feeFlat,
            'fee_percent' => $feePercent,
        ];
    }

    /**
     * @param  array{enabled?: bool, fee_mode?: string, fee_flat?: float|int|string, fee_percent?: float|int|string}  $data
     */
    public static function saveAutoPaystackWithdrawSettings(array $data): void
    {
        $mode = (string) ($data['fee_mode'] ?? 'flat');
        if (! in_array($mode, ['flat', 'percent'], true)) {
            $mode = 'flat';
        }

        static::set(self::AUTO_PAYSTACK_WITHDRAW_KEY, [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'fee_mode' => $mode,
            'fee_flat' => max(0, min(500, round((float) ($data['fee_flat'] ?? 0), 2))),
            'fee_percent' => max(0, min(25, round((float) ($data['fee_percent'] ?? 0), 2))),
        ]);
    }

    public static function autoPaystackWithdrawEnabled(): bool
    {
        return static::autoPaystackWithdrawSettings()['enabled'];
    }

    /**
     * Admin lock for Paystack collections (checkout + wallet top-up).
     *
     * @return array{locked: bool}
     */
    public static function paystackPaymentsSettings(): array
    {
        $raw = static::get(self::PAYSTACK_PAYMENTS_KEY);
        $decoded = is_array($raw)
            ? $raw
            : (is_string($raw) ? json_decode($raw, true) : null);

        if (! is_array($decoded)) {
            return ['locked' => false];
        }

        return [
            'locked' => (bool) ($decoded['locked'] ?? false),
        ];
    }

    public static function paystackPaymentsLocked(): bool
    {
        return static::paystackPaymentsSettings()['locked'];
    }

    /**
     * @param  array{locked?: bool}  $data
     */
    public static function savePaystackPaymentsSettings(array $data): void
    {
        static::set(self::PAYSTACK_PAYMENTS_KEY, [
            'locked' => (bool) ($data['locked'] ?? false),
        ]);
    }

    /**
     * Admin lock for Flutterwave collections (checkout + wallet top-up).
     *
     * @return array{locked: bool}
     */
    public static function flutterwavePaymentsSettings(): array
    {
        $raw = static::get(self::FLUTTERWAVE_PAYMENTS_KEY);
        $decoded = is_array($raw)
            ? $raw
            : (is_string($raw) ? json_decode($raw, true) : null);

        if (! is_array($decoded)) {
            return ['locked' => false];
        }

        return [
            'locked' => (bool) ($decoded['locked'] ?? false),
        ];
    }

    public static function flutterwavePaymentsLocked(): bool
    {
        return static::flutterwavePaymentsSettings()['locked'];
    }

    /**
     * @param  array{locked?: bool}  $data
     */
    public static function saveFlutterwavePaymentsSettings(array $data): void
    {
        static::set(self::FLUTTERWAVE_PAYMENTS_KEY, [
            'locked' => (bool) ($data['locked'] ?? false),
        ]);
    }

    /** Fee charged for a withdrawal to this payout channel (momo|bank). Flat/tier mode only. */
    public static function feeForPayoutType(?string $payoutType, float $amount = 0): float
    {
        $settings = static::withdrawalFeeSettings();
        if (! $settings['enabled'] || $settings['applies_to'] === 'none') {
            return 0.0;
        }

        $type = $payoutType === 'bank' ? 'bank' : 'momo';
        if ($type === 'momo') {
            return (float) $settings['momo_amount'] > 0 ? (float) $settings['momo_amount'] : 0.0;
        }

        $applies = $settings['applies_to'];
        if (! ($applies === 'all' || $applies === 'bank')) {
            return 0.0;
        }

        if ($settings['bank_tiers'] !== []) {
            return static::feeFromBankTiers($amount, $settings['bank_tiers'], (float) $settings['amount']);
        }

        return (float) $settings['amount'] > 0 ? (float) $settings['amount'] : 0.0;
    }

    /** Fee for a withdrawal amount — auto Paystack flat/percent when on, else bank/MoMo channel fee. */
    public static function feeForWithdrawal(float $amount, ?string $payoutType): float
    {
        $auto = static::autoPaystackWithdrawSettings();
        if ($auto['enabled']) {
            if ($auto['fee_mode'] === 'percent' && $auto['fee_percent'] > 0) {
                return max(0, round($amount * ($auto['fee_percent'] / 100), 2));
            }
            if ($auto['fee_mode'] === 'flat') {
                return max(0, round((float) $auto['fee_flat'], 2));
            }
        }

        return static::feeForPayoutType($payoutType, $amount);
    }

    /**
     * Client-facing fee summary for wallet / withdraw screens.
     *
     * @return array{
     *   mode: string,
     *   enabled: bool,
     *   amount: float,
     *   momo_amount: float,
     *   percent: float,
     *   fee_flat: float,
     *   applies_to: string,
     *   auto_paystack: bool,
     *   bank_tiers: list<array{min: float, max: float|null, fee: float}>
     * }
     */
    public static function withdrawalFeePayload(): array
    {
        $auto = static::autoPaystackWithdrawSettings();
        if ($auto['enabled'] && $auto['fee_mode'] === 'percent' && $auto['fee_percent'] > 0) {
            return [
                'mode' => 'percent',
                'enabled' => true,
                'amount' => 0.0,
                'momo_amount' => 0.0,
                'percent' => $auto['fee_percent'],
                'fee_flat' => 0.0,
                'applies_to' => 'all',
                'auto_paystack' => true,
                'bank_tiers' => [],
            ];
        }

        if ($auto['enabled'] && $auto['fee_mode'] === 'flat') {
            $flat = max(0, round((float) $auto['fee_flat'], 2));

            return [
                'mode' => 'flat',
                'enabled' => true,
                'amount' => $flat,
                'momo_amount' => $flat,
                'percent' => 0.0,
                'fee_flat' => $flat,
                'applies_to' => 'all',
                'auto_paystack' => true,
                'bank_tiers' => [],
            ];
        }

        $flat = static::withdrawalFeeSettings();

        return [
            'mode' => 'flat',
            'enabled' => $flat['enabled'],
            'amount' => $flat['amount'],
            'momo_amount' => $flat['momo_amount'],
            'percent' => 0.0,
            'fee_flat' => 0.0,
            'applies_to' => $flat['applies_to'],
            'auto_paystack' => (bool) $auto['enabled'],
            'bank_tiers' => $flat['bank_tiers'],
        ];
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
            // No admin setting yet — enable CityShop MoMo defaults so wallet top-up works out of the box.
            $defaultEnabled = filter_var(
                env('MANUAL_WALLET_TOPUP_DEFAULT_ENABLED', true),
                FILTER_VALIDATE_BOOLEAN
            );

            return [
                'enabled' => $defaultEnabled,
                'instructions' => 'Send payment to one of the CityShop Mobile Money accounts below, then submit your proof and transaction reference so we can credit your wallet.',
                'accounts' => static::defaultCityShopMomoAccounts(),
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
        $hadCustomAccounts = count($accounts) > 0;
        $accounts = static::ensureCityShopMomoAccounts($accounts);

        // Prefer explicit admin flag; if never set, default on. Empty+disabled = not configured yet.
        $explicitEnabled = array_key_exists('enabled', $decoded);
        $enabled = $explicitEnabled
            ? (bool) $decoded['enabled']
            : filter_var(env('MANUAL_WALLET_TOPUP_DEFAULT_ENABLED', true), FILTER_VALIDATE_BOOLEAN);

        if ($explicitEnabled && ! $enabled && ! $hadCustomAccounts) {
            $enabled = filter_var(env('MANUAL_WALLET_TOPUP_DEFAULT_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
        }

        return [
            'enabled' => $enabled,
            'instructions' => (string) ($decoded['instructions'] ?? 'Send payment to one of the CityShop Mobile Money accounts below, then submit your proof and transaction reference so we can credit your wallet.'),
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

    /**
     * Paystack collection fee (wallet top-up + checkout). Admin can use one percent/flat fee or range flats.
     *
     * @return array{
     *   enabled: bool,
     *   mode: string,
     *   percent: float,
     *   flat: float,
     *   tiers: list<array{min: float, max: float|null, fee: float}>
     * }
     */
    public static function paystackFeeSettings(): array
    {
        $raw = static::get(self::PAYSTACK_FEE_KEY);
        $decoded = is_array($raw)
            ? $raw
            : (is_string($raw) ? json_decode($raw, true) : null);

        if (! is_array($decoded)) {
            return static::defaultPaystackFeeSettings();
        }

        $mode = (string) ($decoded['mode'] ?? 'percent');
        if (! in_array($mode, ['percent', 'flat', 'tiers'], true)) {
            $mode = 'percent';
        }

        return [
            'enabled' => (bool) ($decoded['enabled'] ?? true),
            'mode' => $mode,
            'percent' => max(0, min(25, round((float) ($decoded['percent'] ?? 1.95), 4))),
            'flat' => max(0, round((float) ($decoded['flat'] ?? 0), 2)),
            'tiers' => static::normalizePaystackFeeTiers($decoded['tiers'] ?? null),
        ];
    }

    /**
     * @return array{
     *   enabled: bool,
     *   mode: string,
     *   percent: float,
     *   flat: float,
     *   tiers: list<array{min: float, max: float|null, fee: float}>
     * }
     */
    public static function defaultPaystackFeeSettings(): array
    {
        return [
            'enabled' => true,
            'mode' => 'percent',
            'percent' => 1.95,
            'flat' => 0.0,
            'tiers' => static::defaultPaystackFeeTiers(),
        ];
    }

    /**
     * @return list<array{min: float, max: float|null, fee: float}>
     */
    public static function defaultPaystackFeeTiers(): array
    {
        return [
            ['min' => 1.0, 'max' => 99.99, 'fee' => 1.0],
            ['min' => 100.0, 'max' => 999.99, 'fee' => 2.0],
            ['min' => 1000.0, 'max' => null, 'fee' => 5.0],
        ];
    }

    /**
     * @param  mixed  $raw
     * @return list<array{min: float, max: float|null, fee: float}>
     */
    public static function normalizePaystackFeeTiers(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            return static::defaultPaystackFeeTiers();
        }

        $tiers = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $min = max(0, round((float) ($row['min'] ?? 0), 2));
            $fee = max(0, round((float) ($row['fee'] ?? 0), 2));
            $maxRaw = $row['max'] ?? null;
            $max = $maxRaw === null || $maxRaw === '' ? null : max($min, round((float) $maxRaw, 2));
            $tiers[] = ['min' => $min, 'max' => $max, 'fee' => $fee];
        }

        if ($tiers === []) {
            return static::defaultPaystackFeeTiers();
        }

        usort($tiers, fn ($a, $b) => $a['min'] <=> $b['min']);

        return array_values($tiers);
    }

    /**
     * @param  array{
     *   enabled?: bool,
     *   mode?: string,
     *   percent?: float|int|string,
     *   flat?: float|int|string,
     *   tiers?: list<array{min?: float|int|string, max?: float|int|string|null, fee?: float|int|string}>
     * }  $data
     */
    public static function savePaystackFeeSettings(array $data): void
    {
        $mode = (string) ($data['mode'] ?? 'percent');
        if (! in_array($mode, ['percent', 'flat', 'tiers'], true)) {
            $mode = 'percent';
        }

        static::set(self::PAYSTACK_FEE_KEY, [
            'enabled' => (bool) ($data['enabled'] ?? false),
            'mode' => $mode,
            'percent' => max(0, min(25, round((float) ($data['percent'] ?? 1.95), 4))),
            'flat' => max(0, round((float) ($data['flat'] ?? 0), 2)),
            'tiers' => static::normalizePaystackFeeTiers($data['tiers'] ?? null),
        ]);
    }

    /**
     * Client-facing Paystack fee summary for wallet / checkout screens.
     *
     * @return array{
     *   enabled: bool,
     *   mode: string,
     *   percent: float,
     *   flat: float,
     *   tiers: list<array{min: float, max: float|null, fee: float}>
     * }
     */
    public static function paystackFeePayload(): array
    {
        return static::paystackFeeSettings();
    }

    /**
     * @return array{driver: string, failover: bool, alert_mobile_1: string, alert_mobile_2: string}
     */
    public static function smsSettings(): array
    {
        $raw = static::get(self::SMS_KEY);
        $decoded = is_array($raw)
            ? $raw
            : (is_string($raw) ? json_decode($raw, true) : null);

        $envDriver = static::normalizeSmsDriver((string) config('services.sms.driver', 'formula_dc'));
        $driver = is_array($decoded)
            ? static::normalizeSmsDriver((string) ($decoded['driver'] ?? $envDriver))
            : $envDriver;

        return [
            'driver' => $driver,
            'failover' => is_array($decoded) ? (bool) ($decoded['failover'] ?? false) : false,
            'alert_mobile_1' => is_array($decoded) ? (string) ($decoded['alert_mobile_1'] ?? '') : '',
            'alert_mobile_2' => is_array($decoded) ? (string) ($decoded['alert_mobile_2'] ?? '') : '',
            'alert_mobile_3' => is_array($decoded) ? (string) ($decoded['alert_mobile_3'] ?? '') : '',
            'alert_mobile_4' => is_array($decoded) ? (string) ($decoded['alert_mobile_4'] ?? '') : '',
        ];
    }

    /**
     * @param  array{driver?: string, failover?: bool, alert_mobile_1?: string, alert_mobile_2?: string, alert_mobile_3?: string, alert_mobile_4?: string}  $data
     */
    public static function saveSmsSettings(array $data): void
    {
        $driver = static::normalizeSmsDriver((string) ($data['driver'] ?? 'formula_dc'));
        $current = static::smsSettings();
        $failover = array_key_exists('failover', $data)
            ? (bool) $data['failover']
            : ($driver === 'txtconnect' ? false : $current['failover']);

        static::set(self::SMS_KEY, [
            'driver' => $driver,
            'failover' => $failover,
            'alert_mobile_1' => array_key_exists('alert_mobile_1', $data)
                ? trim((string) $data['alert_mobile_1'])
                : $current['alert_mobile_1'],
            'alert_mobile_2' => array_key_exists('alert_mobile_2', $data)
                ? trim((string) $data['alert_mobile_2'])
                : $current['alert_mobile_2'],
            'alert_mobile_3' => array_key_exists('alert_mobile_3', $data)
                ? trim((string) $data['alert_mobile_3'])
                : $current['alert_mobile_3'],
            'alert_mobile_4' => array_key_exists('alert_mobile_4', $data)
                ? trim((string) $data['alert_mobile_4'])
                : $current['alert_mobile_4'],
        ]);
    }

    /** Extra Ghana numbers that always get finance / KYC SMS (withdrawal, deposit, China transfer, Ghana Card). */
    public static function adminAlertNumbers(): array
    {
        $settings = static::smsSettings();

        return array_values(array_filter([
            $settings['alert_mobile_1'],
            $settings['alert_mobile_2'],
            $settings['alert_mobile_3'],
            $settings['alert_mobile_4'],
        ], fn ($phone) => is_string($phone) && trim($phone) !== ''));
    }

    public static function smsDriver(): string
    {
        return static::smsSettings()['driver'];
    }

    public static function smsFailoverEnabled(): bool
    {
        return static::smsSettings()['failover'];
    }

    private static function normalizeSmsDriver(string $driver): string
    {
        $driver = strtolower(trim($driver));

        return match ($driver) {
            'txtconnect', 'txt_connect', 'txt-connect' => 'txtconnect',
            'formula_dc', 'formula', 'formula-dc' => 'formula_dc',
            default => 'formula_dc',
        };
    }
}
