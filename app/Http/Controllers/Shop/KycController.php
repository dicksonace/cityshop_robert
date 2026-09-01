<?php

namespace App\Http\Controllers\Shop;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Notifications\AdminKycSubmittedNotification;
use App\Services\AdminNotifier;
use App\Services\AppNotificationService;
use App\Services\KycService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KycController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::Buyer, UserRole::Seller], true), 403);

        $page = $user->isSeller() ? 'seller/kyc/index' : 'shop/kyc/index';

        return Inertia::render($page, [
            'kyc' => KycService::payload($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
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
            'The system will review your Ghana Card before you can transact with the CityShop wallet. You can still buy with Paystack.',
            ['kyc_id' => $kyc->id, 'status' => $kyc->status?->value],
        );

        return back()->with(
            'success',
            'Ghana Card submitted. The system must approve it before you can transact with the CityShop wallet.',
        );
    }
}
