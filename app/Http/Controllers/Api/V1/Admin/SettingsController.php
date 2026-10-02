<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\FlutterwaveService;
use App\Services\PlatformSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function sms(): JsonResponse
    {
        return response()->json([
            'settings' => PlatformSettings::smsSettings(),
            'providers' => [
                [
                    'id' => 'formula_dc',
                    'label' => 'Formula DC',
                    'configured' => filled(config('services.sms.formula_dc_api_key')),
                ],
                [
                    'id' => 'txtconnect',
                    'label' => 'TxtConnect',
                    'configured' => filled(config('services.sms.txtconnect_api_key')),
                ],
            ],
        ]);
    }

    public function updateSms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'driver' => ['required', 'in:formula_dc,txtconnect'],
            'failover' => ['required', 'boolean'],
            'alert_mobile_1' => ['nullable', 'string', 'max:20'],
            'alert_mobile_2' => ['nullable', 'string', 'max:20'],
            'alert_mobile_3' => ['nullable', 'string', 'max:20'],
            'alert_mobile_4' => ['nullable', 'string', 'max:20'],
        ]);

        PlatformSettings::saveSmsSettings([
            'driver' => $validated['driver'],
            'failover' => (bool) $validated['failover'],
            'alert_mobile_1' => $validated['alert_mobile_1'] ?? '',
            'alert_mobile_2' => $validated['alert_mobile_2'] ?? '',
            'alert_mobile_3' => $validated['alert_mobile_3'] ?? '',
            'alert_mobile_4' => $validated['alert_mobile_4'] ?? '',
        ]);

        $settings = PlatformSettings::smsSettings();
        $message = 'SMS platform saved. Active: '.($settings['driver'] === 'txtconnect' ? 'TxtConnect' : 'Formula DC').'.';
        if ($settings['driver'] === 'txtconnect' && $settings['failover']) {
            $message .= ' Failover is ON — if TxtConnect fails (e.g. sender ID pending), Formula DC will still send.';
        }

        return response()->json([
            'message' => $message,
            'settings' => $settings,
        ]);
    }

    public function testSms(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mobile' => ['required', 'string', 'max:20'],
        ]);

        $result = app(\App\Services\SmsService::class)->sendDetailed(
            $validated['mobile'],
            'CityShop SMS test at '.now()->format('Y-m-d H:i').'. Provider check.',
        );

        $delivered = $result['delivered_via'];
        $label = match ($delivered) {
            'txtconnect' => 'TxtConnect',
            'formula_dc' => 'Formula DC',
            default => null,
        };

        if (! $result['ok']) {
            return response()->json([
                'message' => 'Test SMS failed. Selected '.$result['selected'].'. '.($result['error'] ?? ''),
                'result' => $result,
            ], 422);
        }

        $message = $result['failover_used']
            ? "Test SMS sent via failover {$label}. Selected was {$result['selected']} — that provider failed first."
            : "Test SMS sent via {$label}.";

        return response()->json([
            'message' => $message,
            'result' => $result,
        ]);
    }

    public function paystack(): JsonResponse
    {
        $flutterwaveKeys = [
            'source' => 'none',
            'configured' => false,
            'available' => false,
            'is_test' => false,
            'admin_public_set' => false,
            'admin_secret_set' => false,
            'admin_hash_set' => false,
            'env_public_set' => false,
            'env_secret_set' => false,
            'public_key_masked' => '',
            'secret_key_masked' => '',
            'webhook_hash_set' => false,
        ];

        try {
            $flutterwaveKeys = PlatformSettings::flutterwaveKeysStatus();
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'settings' => PlatformSettings::paystackFeeSettings(),
            'payments_locked' => PlatformSettings::paystackPaymentsLocked(),
            'paystack_payments' => PlatformSettings::paystackPaymentsSettings(),
            'flutterwave_locked' => PlatformSettings::flutterwavePaymentsLocked(),
            'flutterwave_keys' => $flutterwaveKeys,
        ]);
    }

    public function updatePaystack(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'mode' => ['required', 'in:percent,flat,tiers'],
            'percent' => ['required', 'numeric', 'min:0', 'max:25'],
            'flat' => ['required', 'numeric', 'min:0', 'max:500'],
            'tiers' => ['nullable', 'array', 'max:12'],
            'tiers.*.min' => ['required_with:tiers', 'numeric', 'min:0', 'max:1000000'],
            'tiers.*.max' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'tiers.*.fee' => ['required_with:tiers', 'numeric', 'min:0', 'max:500'],
        ]);

        PlatformSettings::savePaystackFeeSettings([
            'enabled' => (bool) $validated['enabled'],
            'mode' => $validated['mode'],
            'percent' => (float) $validated['percent'],
            'flat' => (float) $validated['flat'],
            'tiers' => $validated['tiers'] ?? PlatformSettings::defaultPaystackFeeTiers(),
        ]);

        return response()->json(['message' => 'Paystack fees saved.']);
    }

    public function updatePaystackLock(Request $request): JsonResponse
    {
        $request->validate([
            'locked' => ['nullable', 'boolean'],
            'checkout_enabled' => ['nullable', 'boolean'],
            'recharge_enabled' => ['nullable', 'boolean'],
            'withdrawal_enabled' => ['nullable', 'boolean'],
        ]);

        $validated = [];
        foreach (['locked', 'checkout_enabled', 'recharge_enabled', 'withdrawal_enabled'] as $key) {
            if ($request->exists($key)) {
                $validated[$key] = $request->boolean($key);
            }
        }

        if ($validated === []) {
            return response()->json(['message' => 'Choose which Paystack option to turn on or off.'], 422);
        }

        PlatformSettings::savePaystackPaymentsSettings($validated);
        $settings = PlatformSettings::paystackPaymentsSettings();

        return response()->json([
            'message' => 'Paystack saved. Checkout: '.($settings['checkout_enabled'] ? 'on' : 'off')
                .'. Recharge: '.($settings['recharge_enabled'] ? 'on' : 'off')
                .'. Withdrawals: '.($settings['withdrawal_enabled'] ? 'on' : 'off').'.',
            'payments_locked' => $settings['locked'],
            'paystack_payments' => $settings,
        ]);
    }

    public function updateFlutterwaveLock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locked' => ['required', 'boolean'],
        ]);
        $locked = (bool) $validated['locked'];
        PlatformSettings::saveFlutterwavePaymentsSettings(['locked' => $locked]);

        return response()->json([
            'message' => $locked
                ? 'Flutterwave disabled. Buyers/sellers should use Paystack or manual MoMo / bank.'
                : 'Flutterwave enabled.',
        ]);
    }

    public function updateFlutterwaveKeys(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'public_key' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'webhook_hash' => ['nullable', 'string', 'max:255'],
            'verify' => ['nullable', 'boolean'],
        ]);

        $public = trim((string) ($validated['public_key'] ?? ''));
        $secret = trim((string) ($validated['secret_key'] ?? ''));
        $hash = trim((string) ($validated['webhook_hash'] ?? ''));

        if ($public === '' && $secret === '' && $hash === '') {
            return response()->json(['message' => 'Paste at least a Flutterwave public or secret key.'], 422);
        }

        PlatformSettings::saveFlutterwaveApiKeys([
            'public_key' => $public,
            'secret_key' => $secret,
            'webhook_hash' => $hash,
        ]);

        $status = PlatformSettings::flutterwaveKeysStatus();
        if ($request->boolean('verify', true)) {
            $probe = app(FlutterwaveService::class)->probeCredentials(
                $secret !== '' ? $secret : null,
            );
            if (! $probe['ok']) {
                return response()->json([
                    'message' => 'Keys saved, but Flutterwave rejected them: '.$probe['message'],
                    'flutterwave_keys' => $status,
                    'verified' => false,
                ], 422);
            }

            return response()->json([
                'message' => 'Flutterwave keys saved. '.$probe['message'],
                'flutterwave_keys' => $status,
                'verified' => true,
            ]);
        }

        return response()->json([
            'message' => 'Flutterwave keys saved.',
            'flutterwave_keys' => $status,
        ]);
    }

    public function verifyFlutterwaveKeys(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        $secret = trim((string) ($validated['secret_key'] ?? ''));
        if ($secret !== '') {
            $secret = PlatformSettings::normalizeFlutterwaveSecretKey($secret);
        }

        $probe = app(FlutterwaveService::class)->probeCredentials($secret !== '' ? $secret : null);

        return response()->json([
            'message' => $probe['message'],
            'verified' => $probe['ok'],
            'flutterwave_keys' => PlatformSettings::flutterwaveKeysStatus(),
        ], $probe['ok'] ? 200 : 422);
    }

    public function clearFlutterwaveKeys(): JsonResponse
    {
        PlatformSettings::clearFlutterwaveApiKeys();
        $status = PlatformSettings::flutterwaveKeysStatus();

        return response()->json([
            'message' => $status['configured']
                ? 'Admin Flutterwave keys cleared. Deposits will use the server .env keys.'
                : 'Admin Flutterwave keys cleared. Set keys here or on the server before buyers can deposit.',
            'flutterwave_keys' => $status,
        ]);
    }

    public function withdrawal(): JsonResponse
    {
        return response()->json([
            'settings' => PlatformSettings::withdrawalFeeSettings(),
            'auto_paystack' => PlatformSettings::autoPaystackWithdrawSettings(),
        ]);
    }

    public function updateWithdrawal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'amount' => ['required', 'numeric', 'min:0', 'max:500'],
            'momo_amount' => ['required', 'numeric', 'min:0', 'max:500'],
            'applies_to' => ['required', 'in:bank,momo,all,none'],
            'bank_tiers' => ['nullable', 'array', 'max:10'],
            'bank_tiers.*.min' => ['required_with:bank_tiers', 'numeric', 'min:0'],
            'bank_tiers.*.max' => ['nullable', 'numeric', 'min:0'],
            'bank_tiers.*.fee' => ['required_with:bank_tiers', 'numeric', 'min:0', 'max:500'],
            'auto_paystack_enabled' => ['required', 'boolean'],
            'auto_paystack_fee_mode' => ['nullable', 'in:flat,percent'],
            'auto_paystack_fee_flat' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'auto_paystack_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:25'],
        ]);

        PlatformSettings::saveWithdrawalFeeSettings([
            'enabled' => (bool) $validated['enabled'],
            'amount' => (float) $validated['amount'],
            'momo_amount' => (float) $validated['momo_amount'],
            'applies_to' => $validated['applies_to'],
            'bank_tiers' => $validated['bank_tiers'] ?? PlatformSettings::defaultBankFeeTiers(),
        ]);
        PlatformSettings::saveAutoPaystackWithdrawSettings([
            'enabled' => (bool) $validated['auto_paystack_enabled'],
            'fee_mode' => $validated['auto_paystack_fee_mode'] ?? 'flat',
            'fee_flat' => (float) ($validated['auto_paystack_fee_flat'] ?? 0),
            'fee_percent' => (float) ($validated['auto_paystack_fee_percent'] ?? 0),
        ]);

        return response()->json(['message' => 'Withdrawal settings saved.']);
    }

    public function manualFunding(): JsonResponse
    {
        return response()->json(['settings' => PlatformSettings::manualFundingAccounts()]);
    }

    public function updateManualFunding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'accounts' => ['nullable', 'array', 'max:10'],
            'accounts.*.type' => ['required', 'in:momo,bank'],
            'accounts.*.label' => ['required', 'string', 'max:100'],
            'accounts.*.account_name' => ['required', 'string', 'max:255'],
            'accounts.*.account_number' => ['required', 'string', 'max:50'],
            'accounts.*.network' => ['nullable', 'string', 'max:50'],
            'accounts.*.bank_name' => ['nullable', 'string', 'max:100'],
        ]);

        $networkErrors = [];
        foreach ($validated['accounts'] ?? [] as $index => $account) {
            if (($account['type'] ?? '') === 'momo' && PlatformSettings::normalizeMomoNetwork($account['network'] ?? null) === null) {
                $networkErrors["accounts.{$index}.network"] = 'Select a mobile money network (MTN, Telecel, or AirtelTigo).';
            }
        }
        if ($networkErrors !== []) {
            throw ValidationException::withMessages($networkErrors);
        }

        $accounts = collect($validated['accounts'] ?? [])
            ->map(fn (array $account) => [
                'type' => $account['type'],
                'label' => trim($account['label']),
                'account_name' => trim($account['account_name']),
                'account_number' => trim($account['account_number']),
                'network' => $account['type'] === 'momo'
                    ? PlatformSettings::normalizeMomoNetwork($account['network'] ?? null)
                    : null,
                'bank_name' => $account['type'] === 'bank'
                    ? (trim((string) ($account['bank_name'] ?? ''))
                        ?: (trim((string) ($account['label'] ?? '')) ?: null))
                    : null,
            ])
            ->values()
            ->all();

        PlatformSettings::saveManualFundingAccounts([
            'enabled' => (bool) $validated['enabled'],
            'instructions' => $validated['instructions'] ?? '',
            'accounts' => $accounts,
        ]);

        return response()->json(['message' => 'Manual payment account details saved.']);
    }
}
