<?php

namespace App\Http\Controllers\Seller;

use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\SellerPaymentMethodType;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
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
            'profile' => $profile->only([
                'id',
                'accept_marketplace_payments',
                'accept_direct_payments',
            ]),
            'methods' => $profile->paymentMethods,
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

    public function store(Request $request): RedirectResponse
    {
        $profile = $request->user()->sellerProfile;

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
        $paymentMethod->delete();

        return back()->with('success', 'Payment method removed.');
    }

    public function confirmDirectPayment(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        abort_unless($order->seller_id === $request->user()->id, 403);
        abort_unless($order->payment_channel === PaymentChannel::Direct, 422);
        abort_unless($order->payment_status === PaymentStatus::Pending, 422);

        $orders->confirmDirectPayment($order, $request->user());

        return back()->with('success', 'Direct payment confirmed. You can now process the order.');
    }
}
