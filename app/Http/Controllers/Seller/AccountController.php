<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        return Inertia::render('seller/account', [
            'profile' => $profile ? [
                'business_name' => $profile->business_name,
                'store_name' => $profile->store_name,
                'shop_photo' => $profile->shop_photo,
            ] : null,
        ]);
    }
}
