<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FlutterwaveService;
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
            'paystackPayments' => PlatformSettings::paystackPaymentsSettings(),
            'flutterwaveLocked' => PlatformSettings::flutterwavePaymentsLocked(),
            'flutterwaveKeys' => PlatformSettings::flutterwaveKeysStatus(),
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
            return back()->with('error', 'Choose which Paystack option to turn on or off.');
        }

        PlatformSettings::savePaystackPaymentsSettings($validated);
        $settings = PlatformSettings::paystackPaymentsSettings();

        return back()->with('success', $this->paystackLockMessage($settings));
    }

    /**
     * @param  array{locked: bool, checkout_enabled: bool, recharge_enabled: bool, withdrawal_enabled: bool}  $settings
     */
    private function paystackLockMessage(array $settings): string
    {
        $parts = [
            'Checkout: '.($settings['checkout_enabled'] ? 'on' : 'off'),
            'Recharge: '.($settings['recharge_enabled'] ? 'on' : 'off'),
            'Withdrawals: '.($settings['withdrawal_enabled'] ? 'on' : 'off'),
        ];

        return 'Paystack saved. '.implode('. ', $parts).'.';
    }

    public function updateFlutterwaveLock(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locked' => ['required', 'boolean'],
        ]);

        $locked = (bool) $validated['locked'];
        PlatformSettings::saveFlutterwavePaymentsSettings(['locked' => $locked]);

        return back()->with(
            'success',
            $locked
                ? 'Flutterwave disabled. Buyers can still use Paystack or manual MoMo.'
                : 'Flutterwave enabled for checkout and wallet top-up.',
        );
    }

    public function updateFlutterwaveKeys(Request $request): RedirectResponse
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
            return back()->with('error', 'Paste at least a Flutterwave public or secret key.');
        }

        PlatformSettings::saveFlutterwaveApiKeys([
            'public_key' => $public,
            'secret_key' => $secret,
            'webhook_hash' => $hash,
        ]);

        $shouldVerify = $request->boolean('verify', true);
        if ($shouldVerify) {
            $probe = app(FlutterwaveService::class)->probeCredentials(
                $secret !== '' ? $secret : null,
            );
            if (! $probe['ok']) {
                return back()->with(
                    'error',
                    'Keys saved, but Flutterwave rejected them: '.$probe['message'].' Copy the live secret from Flutterwave → Settings → API keys.',
                );
            }

            return back()->with('success', 'Flutterwave keys saved. '.$probe['message']);
        }

        return back()->with('success', 'Flutterwave keys saved. Use Verify keys to confirm Flutterwave accepts them.');
    }

    public function verifyFlutterwaveKeys(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'secret_key' => ['nullable', 'string', 'max:255'],
        ]);

        $secret = trim((string) ($validated['secret_key'] ?? ''));
        if ($secret !== '') {
            $secret = PlatformSettings::normalizeFlutterwaveSecretKey($secret);
        }

        $probe = app(FlutterwaveService::class)->probeCredentials($secret !== '' ? $secret : null);

        return back()->with($probe['ok'] ? 'success' : 'error', $probe['message']);
    }

    public function clearFlutterwaveKeys(): RedirectResponse
    {
        PlatformSettings::clearFlutterwaveApiKeys();

        $status = PlatformSettings::flutterwaveKeysStatus();
        $message = $status['configured']
            ? 'Admin Flutterwave keys cleared. Deposits will use the server .env keys.'
            : 'Admin Flutterwave keys cleared. Set keys here or on the server before buyers can deposit.';

        return back()->with('success', $message);
    }
}
