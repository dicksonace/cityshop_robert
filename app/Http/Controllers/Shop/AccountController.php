<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\KycService;
use App\Services\PaymentPinService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isBuyer(), 403);

        $user = $request->user();

        return Inertia::render('shop/account', [
            'kyc' => KycService::payload($user, false),
            'hasPaymentPin' => PaymentPinService::hasPin($user),
        ]);
    }
}
