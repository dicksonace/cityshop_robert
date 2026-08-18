<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Notifications\AdminKycSubmittedNotification;
use App\Services\AdminNotifier;
use App\Services\AppNotificationService;
use App\Services\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        return response()->json(['data' => KycService::payload($user)]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $validated = $request->validate([
            'ghana_card_number' => ['required', 'string', 'min:10', 'max:40'],
            'full_name' => ['nullable', 'string', 'max:120'],
            'front' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'back' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'selfie' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $kyc = KycService::submit($user, [
            'ghana_card_number' => $validated['ghana_card_number'],
            'full_name' => $validated['full_name'] ?? $user->name,
            'front' => $request->file('front'),
            'back' => $request->file('back'),
            'selfie' => $request->file('selfie'),
        ]);

        try {
            AdminNotifier::notify(new AdminKycSubmittedNotification($kyc->load('user')));
        } catch (\Throwable $e) {
            report($e);
        }

        AppNotificationService::send(
            $user,
            'kyc_submitted',
            'Ghana Card submitted',
            'Admin will approve, reject, or ask you to improve the photos. You can still buy with Paystack.',
            ['kyc_id' => $kyc->id, 'status' => $kyc->status?->value],
        );

        return response()->json([
            'message' => 'Ghana Card submitted. Admin will review it before you can store money in your wallet.',
            'data' => KycService::payload($user->fresh()),
        ], 201);
    }
}
