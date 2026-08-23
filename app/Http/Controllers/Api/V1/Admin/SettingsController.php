<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
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
        return response()->json([
            'settings' => PlatformSettings::paystackFeeSettings(),
            'payments_locked' => PlatformSettings::paystackPaymentsLocked(),
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
        $validated = $request->validate([
            'locked' => ['required', 'boolean'],
        ]);
        $locked = (bool) $validated['locked'];
        PlatformSettings::savePaystackPaymentsSettings(['locked' => $locked]);

        return response()->json([
            'message' => $locked
                ? 'Paystack disabled. Buyers/sellers should use manual payment / MoMo funding.'
                : 'Paystack enabled.',
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
            'auto_paystack_fee_percent' => ['required', 'numeric', 'min:0', 'max:25'],
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
            'fee_percent' => (float) $validated['auto_paystack_fee_percent'],
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
                    ? (trim((string) ($account['bank_name'] ?? '')) ?: null)
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
