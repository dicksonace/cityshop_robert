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
            'momo_amount' => ['required', 'numeric', 'min:0', 'max:500'],
            'applies_to' => ['required', 'in:bank,momo,all,none'],
            'bank_tiers' => ['nullable', 'array', 'max:10'],
            'bank_tiers.*.min' => ['required_with:bank_tiers', 'numeric', 'min:0', 'max:1000000'],
            'bank_tiers.*.max' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
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

        return back()->with('success', 'Withdrawal settings saved.');
    }
}
