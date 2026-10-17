<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\JunipayService;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class JunipaySettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        PlatformSettings::saveJunipaySettings(self::validatedSettings($request));

        return back()->with('success', 'Junipay settings saved.');
    }

    public function updateKeys(Request $request): RedirectResponse
    {
        [$ok, $message] = self::saveKeys($request);

        return back()->with($ok ? 'success' : 'error', $message);
    }

    public function verifyKeys(): RedirectResponse
    {
        $probe = app(JunipayService::class)->probeCredentials();

        return back()->with($probe['ok'] ? 'success' : 'error', $probe['message']);
    }

    public function clearKeys(): RedirectResponse
    {
        PlatformSettings::clearJunipayKeys();

        return back()->with('success', 'Admin Junipay keys cleared.');
    }

    /**
     * @return array{collections_enabled?: bool, payouts_enabled?: bool, auto_payout?: bool, auto_payout_bank?: bool, sandbox?: bool}
     */
    public static function validatedSettings(Request $request): array
    {
        return $request->validate([
            'collections_enabled' => ['sometimes', 'boolean'],
            'payouts_enabled' => ['sometimes', 'boolean'],
            'auto_payout' => ['sometimes', 'boolean'],
            'auto_payout_bank' => ['sometimes', 'boolean'],
            'sandbox' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array{0: bool, 1: string}
     */
    public static function saveKeys(Request $request): array
    {
        $validated = $request->validate([
            'client_id' => ['nullable', 'string', 'max:100'],
            'private_key' => ['nullable', 'string', 'max:10000'],
        ]);

        $clientId = trim((string) ($validated['client_id'] ?? ''));
        $privateKey = trim((string) ($validated['private_key'] ?? ''));
        if ($clientId === '' && $privateKey === '') {
            return [false, 'Paste the Junipay Client ID or private key.'];
        }

        PlatformSettings::saveJunipayKeys(['client_id' => $clientId, 'private_key' => $privateKey]);

        $probe = app(JunipayService::class)->probeCredentials();
        if (! $probe['ok']) {
            return [false, 'Keys saved, but Junipay did not accept them yet: '.$probe['message']];
        }

        return [true, 'Junipay keys saved. '.$probe['message']];
    }
}
