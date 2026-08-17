<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Services\PaymentPinService;
use App\Services\SellerActivationService;
use App\Services\SmsService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function show(Request $request, SmsService $sms): JsonResponse
    {
        $user = $request->user();
        $profile = $user->sellerProfile;
        $wallet = WalletService::ensure($user);
        $activation = $profile?->activationPayload() ?? [
            'fee_amount' => 0,
            'prompted_at' => null,
            'paid_until' => null,
            'paid_at' => null,
            'is_active' => true,
            'needs_payment' => false,
        ];

        return response()->json([
            'store_name' => $profile?->displayName(),
            'account_mobile' => $user->mobile,
            'order_sms_mobile_1' => $profile?->order_sms_mobile_1,
            'order_sms_mobile_2' => $profile?->order_sms_mobile_2,
            'has_payment_pin' => PaymentPinService::hasPin($user),
            'activation' => $activation,
            'wallet' => [
                'available_balance' => (float) $wallet->available_balance,
            ],
            'sms_hint' => 'Ghana mobile numbers only. Leave blank to use your account mobile.',
        ]);
    }

    public function updateOrderSms(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->sellerProfile;
        abort_unless($profile !== null, 403);

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
            return response()->json([
                'message' => 'Use a different number for the second SMS.',
                'errors' => ['order_sms_mobile_2' => ['Use a different number for the second SMS.']],
            ], 422);
        }

        $profile->update([
            'order_sms_mobile_1' => $first !== '' ? $first : null,
            'order_sms_mobile_2' => $second !== '' ? $second : null,
        ]);

        return response()->json(array_merge($this->show($request, $sms)->getData(true), [
            'message' => 'New order SMS numbers saved. Both numbers get the same alert.',
        ]));
    }

    public function payActivation(Request $request, SellerActivationService $activation): JsonResponse
    {
        $validated = $request->validate([
            'payment_pin' => ['required', 'digits:4'],
        ]);

        try {
            $activation->payFromWallet($request->user(), $validated['payment_pin']);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(array_merge(
            $this->show($request, app(SmsService::class))->getData(true),
            ['message' => 'Service fee paid. Your store is active for 1 year.']
        ));
    }
}
