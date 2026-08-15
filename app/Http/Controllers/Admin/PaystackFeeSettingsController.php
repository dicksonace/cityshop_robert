<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaystackFeeSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/paystack-fees/settings', [
            'settings' => PlatformSettings::paystackFeeSettings(),
            'paymentsLocked' => PlatformSettings::paystackPaymentsLocked(),
        ]);
    }

    public function update(Request $request): RedirectResponse
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

        return back()->with('success', 'Paystack fees saved.');
    }

    public function updateLock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locked' => ['required', 'boolean'],
        ]);

        $locked = (bool) $validated['locked'];
        PlatformSettings::savePaystackPaymentsSettings(['locked' => $locked]);

        return back()->with(
            'success',
            $locked
                ? 'Paystack disabled. Buyers/sellers should use manual payment / MoMo funding.'
                : 'Paystack enabled. Checkout and wallet top-up via Paystack are on.',
        );
    }
}
