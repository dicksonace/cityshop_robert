<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\PaymentPinService;
use App\Services\SellerActivationService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ActivationController extends Controller
{
    public function __construct(private SellerActivationService $activation) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->sellerProfile;
        $wallet = WalletService::ensure($user);

        return Inertia::render('seller/activation', [
            'activation' => $profile?->activationPayload() ?? [
                'fee_amount' => 0,
                'prompted_at' => null,
                'paid_until' => null,
                'paid_at' => null,
                'is_active' => true,
                'needs_payment' => false,
            ],
            'wallet' => $wallet->toFrontendArray(),
            'hasPaymentPin' => PaymentPinService::hasPin($user),
        ]);
    }

    public function pay(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_pin' => ['required', 'digits:4'],
        ]);

        try {
            $this->activation->payFromWallet($request->user(), $validated['payment_pin']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'amount' => [$e->getMessage()],
            ]);
        }

        return redirect()->route('seller.dashboard')->with('success', 'Service fee paid. Your store is active for 1 year.');
    }
}
