<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
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
                'order_sms_mobile_1' => $profile->order_sms_mobile_1,
                'order_sms_mobile_2' => $profile->order_sms_mobile_2,
            ] : null,
            'accountMobile' => $user->mobile,
        ]);
    }

    public function updateOrderSms(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->isSeller() && $user->sellerProfile, 403);

        $ghanaMobile = function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            if (! app(SmsService::class)->normalizeGhanaMsisdn((string) $value)) {
                $fail('Enter a valid Ghana mobile number.');
            }
        };

        $validated = $request->validate([
            'order_sms_mobile_1' => ['nullable', 'string', 'max:20', $ghanaMobile],
            'order_sms_mobile_2' => ['nullable', 'string', 'max:20', $ghanaMobile],
        ]);

        $first = trim((string) ($validated['order_sms_mobile_1'] ?? ''));
        $second = trim((string) ($validated['order_sms_mobile_2'] ?? ''));
        $sms = app(SmsService::class);
        $firstMsisdn = $first !== '' ? $sms->normalizeGhanaMsisdn($first) : null;
        $secondMsisdn = $second !== '' ? $sms->normalizeGhanaMsisdn($second) : null;

        if ($firstMsisdn && $secondMsisdn && $firstMsisdn === $secondMsisdn) {
            return back()->withErrors([
                'order_sms_mobile_2' => 'Use a different number for the second SMS.',
            ]);
        }

        $user->sellerProfile->update([
            'order_sms_mobile_1' => $first !== '' ? $first : null,
            'order_sms_mobile_2' => $second !== '' ? $second : null,
        ]);

        return back()->with('success', 'New order SMS numbers saved. Both numbers get the same alert.');
    }
}
