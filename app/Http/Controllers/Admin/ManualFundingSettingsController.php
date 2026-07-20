<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        $networkErrors = [];
        foreach ($validated['accounts'] ?? [] as $index => $account) {
            if (($account['type'] ?? '') !== 'momo') {
                continue;
            }

            if (PlatformSettings::normalizeMomoNetwork($account['network'] ?? null) === null) {
                $networkErrors["accounts.{$index}.network"] = 'Select a mobile money network (MTN, Telecel, or AirtelTigo).';
            }
        }

        if ($networkErrors !== []) {
            throw ValidationException::withMessages($networkErrors);
        }

        $accounts = collect($validated['accounts'] ?? [])
            ->map(function (array $account) {
                return [
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
                ];
            })
            ->values()
            ->all();

        PlatformSettings::saveManualFundingAccounts([
            'enabled' => (bool) $validated['enabled'],
            'instructions' => $validated['instructions'] ?? '',
            'accounts' => $accounts,
        ]);

        return back()->with('success', 'Manual payment account details saved.');
    }
}
