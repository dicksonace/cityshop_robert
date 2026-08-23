<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsSettingsController extends Controller
{
    public function edit(): Response
    {
        $settings = PlatformSettings::smsSettings();

        return Inertia::render('admin/sms/settings', [
            'settings' => $settings,
            'providers' => [
                [
                    'id' => 'formula_dc',
                    'label' => 'Formula DC',
                    'configured' => filled(config('services.sms.formula_dc_api_key')),
                    'sender' => (string) config('services.sms.formula_dc_sender', 'Cityshop'),
                ],
                [
                    'id' => 'txtconnect',
                    'label' => 'TxtConnect',
                    'configured' => filled(config('services.sms.txtconnect_api_key')),
                    'sender' => (string) config('services.sms.txtconnect_sender', 'CityShop'),
                ],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
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

        return back()->with(
            'success',
            $settings['driver'] === 'txtconnect' && $settings['failover']
                ? 'SMS platform saved as TxtConnect. Failover is ON — if TxtConnect fails, Formula DC will still send.'
                : 'SMS platform saved. Active: '.($settings['driver'] === 'txtconnect' ? 'TxtConnect' : 'Formula DC').'.'
        );
    }
}
