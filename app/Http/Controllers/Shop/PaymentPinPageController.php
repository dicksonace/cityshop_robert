<?php

namespace App\Http\Controllers\Shop;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\PaymentPinService;
use App\Support\ResetChannel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentPinPageController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $page = $user->isSeller() ? 'seller/payment-pin' : 'shop/payment-pin';

        return Inertia::render($page, [
            'hasPaymentPin' => PaymentPinService::hasPin($user),
            'hasEmail' => filled($user->email),
            'hasMobile' => filled($user->mobile),
            'status' => $request->session()->get('status'),
            'emailHint' => $request->session()->get('email_hint'),
            'hint' => $request->session()->get('hint'),
            'via' => $request->session()->get('via'),
        ]);
    }
}
