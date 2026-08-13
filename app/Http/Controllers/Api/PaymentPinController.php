<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\PaymentPinService;
use App\Support\ResetChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentPinController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (PaymentPinService::hasPin($user)) {
            return response()->json([
                'message' => 'Payment PIN already set. Use change or reset instead.',
            ], 422);
        }

        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        PaymentPinService::set($user, $data['pin']);

        return response()->json([
            'message' => 'Payment PIN set.',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        PaymentPinService::change($request->user(), $data['current_pin'], $data['pin']);

        return response()->json([
            'message' => 'Payment PIN updated.',
            'user' => new UserResource($request->user()->fresh()),
        ]);
    }

    public function forgot(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! PaymentPinService::hasPin($user)) {
            return response()->json([
                'message' => 'No payment PIN is set yet.',
            ], 422);
        }

        $validated = $request->validate([
            'via' => ['nullable', 'in:email,sms'],
        ]);

        $via = ResetChannel::parse($validated['via'] ?? ResetChannel::EMAIL);
        PaymentPinService::sendResetCode($user, $via);

        $hint = ResetChannel::hint($via, $user->email, $user->mobile);
        $destination = $via === ResetChannel::SMS ? 'phone' : 'email';

        return response()->json([
            'message' => "A reset code was sent to your {$destination}.",
            'via' => $via,
            'hint' => $hint,
            'email_hint' => $via === ResetChannel::EMAIL ? $hint : null,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ]);

        PaymentPinService::resetWithCode($request->user(), $data['code'], $data['pin']);

        return response()->json([
            'message' => 'Payment PIN reset successfully.',
            'user' => new UserResource($request->user()->fresh()),
        ]);
    }
}
