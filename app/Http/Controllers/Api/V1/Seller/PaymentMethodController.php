<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Enums\SellerPaymentMethodType;
use App\Http\Controllers\Controller;
use App\Services\SellerPaymentMethodSecurityService;
use App\Support\GhanaBanks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile;
        $profile->load('paymentMethods');

        return response()->json([
            'profile' => [
                'accept_marketplace_payments' => (bool) $profile->accept_marketplace_payments,
                'accept_direct_payments' => (bool) $profile->accept_direct_payments,
                'cash_on_delivery_enabled' => $profile->acceptsCashOnDelivery(),
                'payment_methods_locked' => $profile->paymentMethodsAreLocked(),
                'payment_methods_lock_reason' => $profile->payment_methods_lock_reason,
            ],
            'methods' => $profile->paymentMethods->map(fn ($method) => [
                'id' => $method->id,
                'type' => $method->type->value,
                'label' => $method->displayLabel(),
                'account_name' => $method->account_name,
                'account_number' => $method->account_number,
                'network' => $method->network,
                'bank_name' => $method->bank_name,
                'instructions' => $method->instructions,
                'is_active' => $method->is_active,
                'is_default' => $method->is_default,
                'is_disabled' => $method->isDisabled(),
                'disabled_reason' => $method->disabled_reason,
            ])->values(),
            'types' => collect(SellerPaymentMethodType::creatable())->map(fn ($t) => [
                'value' => $t->value,
                'label' => ucwords(str_replace('_', ' ', $t->value)),
            ])->values(),
            'banks' => collect(GhanaBanks::OPTIONS)->map(fn ($label, $id) => [
                'id' => $id,
                'label' => $label,
            ])->values(),
            'networks' => ['MTN', 'Telecel', 'AirtelTigo'],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile;

        $validated = $request->validate([
            'accept_marketplace_payments' => ['required', 'boolean'],
            'accept_direct_payments' => ['required', 'boolean'],
            'cash_on_delivery_enabled' => ['required', 'boolean'],
        ]);

        if (! $validated['accept_marketplace_payments'] && ! $validated['accept_direct_payments']) {
            return response()->json(['message' => 'Enable at least one payment mode.'], 422);
        }

        if ($validated['accept_direct_payments'] && $profile->paymentMethods()->where('is_active', true)->count() === 0) {
            return response()->json(['message' => 'Add at least one active payment method before enabling direct payments.'], 422);
        }

        $profile->update($validated);

        $payload = $this->index($request)->getData(true);
        $payload['message'] = 'Payment settings updated.';

        return response()->json($payload);
    }

    public function store(Request $request, SellerPaymentMethodSecurityService $security): JsonResponse
    {
        $profile = $request->user()->sellerProfile;

        try {
            $security->assertCanManagePaymentMethods($profile);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_column(SellerPaymentMethodType::creatable(), 'value'))],
            'label' => ['nullable', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'network' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
        ]);

        if (($validated['type'] ?? '') === SellerPaymentMethodType::Bank->value) {
            $request->validate([
                'bank_name' => ['required', 'string', 'max:100'],
                'account_number' => ['required', 'string', 'max:100'],
            ]);
            $bankName = GhanaBanks::resolveName($validated['bank_name'] ?? null);
            if (! $bankName) {
                return response()->json([
                    'message' => 'Select a bank from the list.',
                    'errors' => ['bank_name' => ['Select a bank from the list.']],
                ], 422);
            }
            $validated['bank_name'] = $bankName;
            $validated['network'] = null;
        } else {
            $validated['bank_name'] = null;
            $request->validate([
                'account_number' => ['required', 'string', 'max:100'],
                'network' => ['required', 'string', 'max:50'],
            ]);
        }

        try {
            $security->assertAccountNotBlocked($profile, $validated['account_number'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($validated['is_default'] ?? false) {
            $profile->paymentMethods()->update(['is_default' => false]);
        }

        $validated['is_active'] = true;
        $profile->paymentMethods()->create($validated);

        return response()->json(array_merge(
            $this->index($request)->getData(true),
            ['message' => 'Payment method added.']
        ), 201);
    }

    public function destroy(Request $request, int $method): JsonResponse
    {
        $profile = $request->user()->sellerProfile;
        $paymentMethod = $profile->paymentMethods()->whereKey($method)->firstOrFail();

        if ($paymentMethod->isDisabled()) {
            return response()->json(['message' => 'This payment method was disabled by admin and cannot be removed.'], 422);
        }

        $paymentMethod->delete();

        return response()->json(array_merge(
            $this->index($request)->getData(true),
            ['message' => 'Payment method removed.']
        ));
    }
}
