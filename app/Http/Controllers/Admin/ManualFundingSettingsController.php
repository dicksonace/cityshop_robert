<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualFundingSettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/manual-funding/settings', [
            'settings' => PlatformSettings::manualFundingAccounts(),
        ]);
    }

    public function update(Request $request): RedirectResponse
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

        $accounts = collect($validated['accounts'] ?? [])
            ->map(function (array $account) {
                return [
                    'type' => $account['type'],
                    'label' => trim($account['label']),
                    'account_name' => trim($account['account_name']),
                    'account_number' => trim($account['account_number']),
                    'network' => $account['type'] === 'momo' ? ($account['network'] ?? null) : null,
                    'bank_name' => $account['type'] === 'bank' ? ($account['bank_name'] ?? null) : null,
                ];
            })
            ->values()
            ->all();

        PlatformSettings::saveManualFundingAccounts([
            'enabled' => $validated['enabled'],
            'instructions' => $validated['instructions'] ?? '',
            'accounts' => $accounts,
        ]);

        return back()->with('success', 'Manual payment account details saved.');
    }
}
