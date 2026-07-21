<?php

namespace App\Http\Controllers\Seller;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\SellerPaymentMethodType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\SellerPaymentMethodSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function index(Request $request): Response
    {
        $profile = $request->user()->sellerProfile;
        $profile->load('paymentMethods');

        return Inertia::render('seller/payment-methods/index', [
            'profile' => [
                'id' => $profile->id,
                'accept_marketplace_payments' => $profile->accept_marketplace_payments,
                'accept_direct_payments' => $profile->accept_direct_payments,
                'payment_methods_locked' => $profile->paymentMethodsAreLocked(),
                'payment_methods_lock_reason' => $profile->payment_methods_lock_reason,
            ],
            'methods' => $profile->paymentMethods->map(fn ($method) => [
                'id' => $method->id,
                'type' => $method->type->value,
                'label' => $method->label,
                'account_name' => $method->account_name,
                'account_number' => $method->account_number,
                'network' => $method->network,
                'bank_name' => $method->bank_name,
                'instructions' => $method->instructions,
                'is_active' => $method->is_active,
                'is_default' => $method->is_default,
                'is_disabled' => $method->isDisabled(),
                'disabled_reason' => $method->disabled_reason,
                'disabled_at' => $method->disabled_at?->toIso8601String(),
            ]),
            'types' => collect(SellerPaymentMethodType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => ucwords(str_replace('_', ' ', $t->value)),
            ]),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $profile = $request->user()->sellerProfile;

        $validated = $request->validate([
            'accept_marketplace_payments' => ['required', 'boolean'],
            'accept_direct_payments' => ['required', 'boolean'],
        ]);

        if (! $validated['accept_marketplace_payments'] && ! $validated['accept_direct_payments']) {
            return back()->with('error', 'Enable at least one payment mode.');
        }

        if ($validated['accept_direct_payments'] && $profile->paymentMethods()->where('is_active', true)->count() === 0) {
            return back()->with('error', 'Add at least one active payment method before enabling direct payments.');
        }

        $profile->update($validated);

        return back()->with('success', 'Payment settings updated.');
    }

    public function store(Request $request, SellerPaymentMethodSecurityService $security): RedirectResponse
    {
        $profile = $request->user()->sellerProfile;

        try {
            $security->assertCanManagePaymentMethods($profile);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_column(SellerPaymentMethodType::cases(), 'value'))],
            'label' => ['nullable', 'string', 'max:100'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'network' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'is_default' => ['boolean'],
        ]);

        try {
            $security->assertAccountNotBlocked($profile, $validated['account_number'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($validated['is_default'] ?? false) {
            $profile->paymentMethods()->update(['is_default' => false]);
        }

        $profile->paymentMethods()->create($validated);

        return back()->with('success', 'Payment method added.');
    }

    public function destroy(Request $request, int $method): RedirectResponse
    {
        $profile = $request->user()->sellerProfile;
        $paymentMethod = $profile->paymentMethods()->whereKey($method)->firstOrFail();

        if ($paymentMethod->isDisabled()) {
            return back()->with('error', 'This payment method was disabled by admin and cannot be removed.');
        }

        $paymentMethod->delete();

        return back()->with('success', 'Payment method removed.');
    }

    public function confirmDirectPayment(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        abort_unless($order->seller_id === $request->user()->id, 403);
        abort_unless($order->payment_channel === PaymentChannel::Direct, 422);
        abort_unless($order->payment_status === PaymentStatus::Pending, 422);

        $orders->confirmDirectPayment($order, $request->user());

        return back()->with('success', 'Customer manual payment confirmed. You can now process the order.');
    }

    public function rejectDirectPayment(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        abort_unless($order->seller_id === $request->user()->id, 403);
        abort_unless($order->payment_channel === PaymentChannel::Direct, 422);
        abort_unless($order->payment_status === PaymentStatus::Pending, 422);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $orders->rejectDirectPayment($order, $request->user(), $validated['reason']);

        return back()->with('success', 'Payment claim rejected. The buyer can submit a new payment reference.');
    }
}
