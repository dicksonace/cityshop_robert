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
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'amount' => ['required', 'numeric', 'min:0', 'max:500'],
            'applies_to' => ['required', 'in:bank,momo,all,none'],
        ]);

        PlatformSettings::saveWithdrawalFeeSettings([
            'enabled' => (bool) $validated['enabled'],
            'amount' => (float) $validated['amount'],
            'applies_to' => $validated['applies_to'],
        ]);

        return back()->with('success', 'Withdrawal fee settings saved.');
    }
}
