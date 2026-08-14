<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChinaTransferFormField;
use App\Models\ChinaTransferPaymentMethod;
use App\Models\ChinaTransferRate;
use App\Services\ChinaTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChinaTransferSettingsController extends Controller
{
    public function __construct(private ChinaTransferService $transfers) {}

    public function edit(): Response
    {
        $settings = $this->transfers->settings();

        return Inertia::render('admin/china-transfer/settings', [
            'settings' => [
                'enabled' => $settings->enabled,
                'channel' => 'alipay',
                'instructions' => $settings->instructions,
            ],
            'currentRate' => ($rate = $this->transfers->currentRate())
                ? $this->transfers->ratePayload($rate)
                : null,
            'rates' => ChinaTransferRate::query()
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (ChinaTransferRate $rate) => $this->transfers->ratePayload($rate)),
            'methods' => ChinaTransferPaymentMethod::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ChinaTransferPaymentMethod $m) => $this->transfers->methodPayload($m)),
            'fields' => ChinaTransferFormField::query()
                ->orderBy('group')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ChinaTransferFormField $f) => $this->transfers->fieldPayload($f)),
            'fieldTypes' => ChinaTransferFormField::TYPES,
            'open' => $this->transfers->isOpen(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings = $this->transfers->settings();
        $settings->update([
            'enabled' => $validated['enabled'],
            'channel' => 'alipay',
            'instructions' => $validated['instructions'] ?? null,
        ]);

        return back()->with('success', 'China Transfer settings saved. Alipay only — WeChat Pay is off.');
    }

    public function publishRate(Request $request): RedirectResponse
    {
        foreach (['daily_max_ghs', 'monthly_max_ghs', 'max_per_day', 'approval_above_ghs', 'effective_from'] as $key) {
            if ($request->input($key) === '') {
                $request->merge([$key => null]);
            }
        }

        $validated = $request->validate([
            'ghs_per_rmb' => ['required', 'numeric', 'min:0.0001'],
            'fee_mode' => ['required', 'in:flat,percent'],
            'fee_value' => ['required', 'numeric', 'min:0'],
            'min_ghs' => ['required', 'numeric', 'min:1'],
            'max_ghs' => ['required', 'numeric', 'gte:min_ghs'],
            'daily_max_ghs' => ['nullable', 'numeric', 'min:1'],
            'monthly_max_ghs' => ['nullable', 'numeric', 'min:1'],
            'max_per_day' => ['nullable', 'integer', 'min:1'],
            'approval_above_ghs' => ['nullable', 'numeric', 'min:1'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $this->transfers->publishRate($request->user(), $validated);

        return back()->with('success', 'New rate published. Existing transfers keep their locked rate.');
    }

    public function storeMethod(Request $request): RedirectResponse
    {
        $validated = $this->methodRules($request);
        $validated['qr_path'] = $this->storeQr($request);
        $validated['sort_order'] = $validated['sort_order'] ?? ChinaTransferPaymentMethod::max('sort_order') + 1;
        unset($validated['qr']);

        ChinaTransferPaymentMethod::create($validated);

        return back()->with('success', 'Payment method added.');
    }

    public function updateMethod(Request $request, ChinaTransferPaymentMethod $method): RedirectResponse
    {
        $validated = $this->methodRules($request, false);
        if ($request->hasFile('qr')) {
            $validated['qr_path'] = $this->storeQr($request);
        }
        unset($validated['qr']);
        $method->update($validated);

        return back()->with('success', 'Payment method updated.');
    }

    public function destroyMethod(ChinaTransferPaymentMethod $method): RedirectResponse
    {
        $method->update(['active' => false]);

        return back()->with('success', 'Payment method deactivated.');
    }

    public function storeField(Request $request): RedirectResponse
    {
        $validated = $this->fieldRules($request);
        $validated['name'] = $validated['name'] ?: Str::slug($validated['label'], '_');
        $validated['sort_order'] = $validated['sort_order'] ?? 99;
        ChinaTransferFormField::create($validated);

        return back()->with('success', 'Form field added.');
    }

    public function updateField(Request $request, ChinaTransferFormField $field): RedirectResponse
    {
        $field->update($this->fieldRules($request, false));

        return back()->with('success', 'Form field updated.');
    }

    public function destroyField(ChinaTransferFormField $field): RedirectResponse
    {
        $field->update(['active' => false]);

        return back()->with('success', 'Form field deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function methodRules(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        $validated = $request->validate([
            'name' => [$required, 'string', 'max:120'],
            'type' => [$required, 'in:momo,bank,wallet,other'],
            'account_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:80'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'network' => ['nullable', 'string', 'max:40'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'proof_required' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'qr' => ['nullable', 'image', 'max:4096'],
        ]);

        $validated['proof_required'] = $request->boolean('proof_required', true);
        $validated['active'] = $request->boolean('active', true);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldRules(Request $request, bool $creating = true): array
    {
        $required = $creating ? 'required' : 'sometimes';

        $validated = $request->validate([
            'group' => [$required, 'in:recipient,payment'],
            'type' => [$required, 'in:'.implode(',', ChinaTransferFormField::TYPES)],
            'name' => ['nullable', 'string', 'max:80'],
            'label' => [$required, 'string', 'max:120'],
            'placeholder' => ['nullable', 'string', 'max:160'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'required' => ['sometimes', 'boolean'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:80'],
            'file_types' => ['nullable', 'array'],
            'file_types.*' => ['string', 'max:12'],
            'max_size_kb' => ['nullable', 'integer', 'min:100', 'max:20480'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $validated['required'] = $request->boolean('required', true);
        $validated['active'] = $request->boolean('active', true);

        return $validated;
    }

    private function storeQr(Request $request): ?string
    {
        if (! $request->hasFile('qr')) {
            return null;
        }

        return $request->file('qr')->store('china-transfers/methods', 'public');
    }
}
