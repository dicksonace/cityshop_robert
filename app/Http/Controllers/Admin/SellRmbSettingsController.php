<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellRmbFormField;
use App\Models\SellRmbRate;
use App\Models\SellRmbReceiveMethod;
use App\Services\SellRmbService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SellRmbSettingsController extends Controller
{
    public function __construct(private SellRmbService $sellRmb) {}

    public function edit(): Response
    {
        $settings = $this->sellRmb->settings();

        return Inertia::render('admin/sell-rmb/settings', [
            'settings' => [
                'enabled' => $settings->enabled,
                'instructions' => $settings->instructions,
                'receive_instructions' => $settings->receive_instructions,
            ],
            'currentRate' => ($rate = $this->sellRmb->currentRate())
                ? $this->sellRmb->ratePayload($rate)
                : null,
            'rates' => SellRmbRate::query()
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (SellRmbRate $rate) => $this->sellRmb->ratePayload($rate)),
            'methods' => SellRmbReceiveMethod::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SellRmbReceiveMethod $m) => $this->sellRmb->methodPayload($m)),
            'fields' => SellRmbFormField::query()
                ->orderBy('group')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SellRmbFormField $f) => $this->sellRmb->fieldPayload($f)),
            'fieldTypes' => SellRmbFormField::TYPES,
            'open' => $this->sellRmb->isOpen(),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'receive_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        $settings = $this->sellRmb->settings();
        $settings->update([
            'enabled' => $validated['enabled'],
            'instructions' => $validated['instructions'] ?? null,
            'receive_instructions' => $validated['receive_instructions'] ?? null,
        ]);

        return back()->with('success', 'Sell RMB settings saved.');
    }

    public function publishRate(Request $request): RedirectResponse
    {
        foreach (['daily_max_rmb', 'monthly_max_rmb', 'max_per_day', 'approval_above_rmb', 'effective_from'] as $key) {
            if ($request->input($key) === '') {
                $request->merge([$key => null]);
            }
        }

        $validated = $request->validate([
            'usd_per_rmb' => ['required', 'numeric', 'min:0.000001'],
            'ghs_per_usd' => ['required', 'numeric', 'min:0.0001'],
            'fee_mode' => ['required', 'in:flat,percent'],
            'fee_value' => ['required', 'numeric', 'min:0'],
            'min_rmb' => ['required', 'numeric', 'min:1'],
            'max_rmb' => ['required', 'numeric', 'gte:min_rmb'],
            'daily_max_rmb' => ['nullable', 'numeric', 'min:1'],
            'monthly_max_rmb' => ['nullable', 'numeric', 'min:1'],
            'max_per_day' => ['nullable', 'integer', 'min:1'],
            'approval_above_rmb' => ['nullable', 'numeric', 'min:1'],
            'effective_from' => ['nullable', 'date'],
        ]);

        $this->sellRmb->publishRate($request->user(), $validated);

        return back()->with('success', 'New buying rate published. Existing requests keep their locked rate.');
    }

    public function storeMethod(Request $request): RedirectResponse
    {
        $validated = $this->methodRules($request);
        $validated['qr_path'] = $this->storeQr($request);
        $validated['sort_order'] = $validated['sort_order'] ?? SellRmbReceiveMethod::max('sort_order') + 1;
        unset($validated['qr']);

        SellRmbReceiveMethod::create($validated);

        return back()->with('success', 'Receive method added.');
    }

    public function updateMethod(Request $request, SellRmbReceiveMethod $method): RedirectResponse
    {
        $validated = $this->methodRules($request, false);
        if ($request->hasFile('qr')) {
            $validated['qr_path'] = $this->storeQr($request);
        }
        unset($validated['qr']);
        $method->update($validated);

        return back()->with('success', 'Receive method updated.');
    }

    public function destroyMethod(SellRmbReceiveMethod $method): RedirectResponse
    {
        $method->update(['active' => false]);

        return back()->with('success', 'Receive method deactivated.');
    }

    public function storeField(Request $request): RedirectResponse
    {
        $validated = $this->fieldRules($request);
        $validated['name'] = $validated['name'] ?: Str::slug($validated['label'], '_');
        $validated['sort_order'] = $validated['sort_order'] ?? 99;
        SellRmbFormField::create($validated);

        return back()->with('success', 'Form field added.');
    }

    public function updateField(Request $request, SellRmbFormField $field): RedirectResponse
    {
        $field->update($this->fieldRules($request, false));

        return back()->with('success', 'Form field updated.');
    }

    public function destroyField(SellRmbFormField $field): RedirectResponse
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
            'type' => [$required, 'in:alipay,wechat,bank,other'],
            'account_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:80'],
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
            'group' => [$required, 'in:recipient,payment,payout'],
            'type' => [$required, 'in:'.implode(',', SellRmbFormField::TYPES)],
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

        return $request->file('qr')->store('sell-rmb/methods', 'public');
    }
}
