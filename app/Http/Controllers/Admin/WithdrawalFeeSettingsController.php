<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WithdrawalFeeSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/withdrawal-fees/settings', [
            'settings' => PlatformSettings::withdrawalFeeSettings(),
            'autoPaystack' => PlatformSettings::autoPaystackWithdrawSettings(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'amount' => ['required', 'numeric', 'min:0', 'max:500'],
            'momo_amount' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'applies_to' => ['required', 'in:bank,momo,all,none'],
            'bank_tiers' => ['nullable', 'array', 'max:10'],
            'bank_tiers.*.min' => ['required_with:bank_tiers', 'numeric', 'min:0', 'max:1000000'],
            'bank_tiers.*.max' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'bank_tiers.*.fee' => ['required_with:bank_tiers', 'numeric', 'min:0', 'max:500'],
            'momo_tiers' => ['nullable', 'array', 'max:10'],
            'momo_tiers.*.min' => ['required_with:momo_tiers', 'numeric', 'min:0', 'max:1000000'],
            'momo_tiers.*.max' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'momo_tiers.*.mode' => ['nullable', 'in:flat,percent'],
            'momo_tiers.*.fee' => ['required_with:momo_tiers', 'numeric', 'min:0', 'max:500'],
            'auto_paystack_enabled' => ['required', 'boolean'],
            'auto_paystack_fee_mode' => ['nullable', 'in:flat,percent'],
            'auto_paystack_fee_flat' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'auto_paystack_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:25'],
        ]);

        $fee = [
            'enabled' => (bool) $validated['enabled'],
            'amount' => (float) $validated['amount'],
            'momo_amount' => (float) ($validated['momo_amount'] ?? 0),
            'applies_to' => $validated['applies_to'],
            'bank_tiers' => $validated['bank_tiers'] ?? PlatformSettings::defaultBankFeeTiers(),
        ];
        if (array_key_exists('momo_tiers', $validated)) {
            $fee['momo_tiers'] = $validated['momo_tiers'] ?? [];
        }

        PlatformSettings::saveWithdrawalFeeSettings($fee);

        PlatformSettings::saveAutoPaystackWithdrawSettings([
            'enabled' => (bool) $validated['auto_paystack_enabled'],
            'fee_mode' => $validated['auto_paystack_fee_mode'] ?? 'flat',
            'fee_flat' => (float) ($validated['auto_paystack_fee_flat'] ?? 0),
            'fee_percent' => (float) ($validated['auto_paystack_fee_percent'] ?? 0),
        ]);

        return back()->with('success', 'Withdrawal settings saved.');
    }
}
