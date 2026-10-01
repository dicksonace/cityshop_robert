<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class QrPaymentController extends Controller
{
    public function receive(Request $request): RedirectResponse
    {
        return $this->redirectToWallet($request);
    }

    public function pay(Request $request): RedirectResponse
    {
        return $this->redirectToWallet($request);
    }

    public function resolve(Request $request): RedirectResponse
    {
        return $this->redirectToWallet($request);
    }

    public function submitPay(Request $request): RedirectResponse
    {
        return $this->redirectToWallet($request);
    }

    private function redirectToWallet(Request $request): RedirectResponse
    {
        $user = $request->user();

        return redirect()->route($user?->isSeller() ? 'seller.wallet' : 'wallet.index');
    }
}
