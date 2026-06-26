<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerInviteStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Models\SellerRegistrationInvite;
use App\Services\SellerRegistrationInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SellerInviteController extends Controller
{
    public function index(Request $request): Response
    {
        $invites = SellerRegistrationInvite::with(['creator:id,name', 'sellerProfile.user:id,name,email'])
            ->latest()
            ->paginate(20)
            ->through(fn (SellerRegistrationInvite $invite) => [
                'id' => $invite->id,
                'email' => $invite->email,
                'name' => $invite->name,
                'notes' => $invite->notes,
                'status' => $invite->status->value,
                'expires_at' => $invite->expires_at?->toIso8601String(),
                'used_at' => $invite->used_at?->toIso8601String(),
                'created_at' => $invite->created_at?->toIso8601String(),
                'registration_url' => $invite->status === SellerInviteStatus::Pending && $invite->isValid()
                    ? $invite->registrationUrl()
                    : null,
                'creator' => $invite->creator ? ['name' => $invite->creator->name] : null,
                'seller' => $invite->sellerProfile?->user
                    ? ['name' => $invite->sellerProfile->user->name, 'email' => $invite->sellerProfile->user->email]
                    : null,
            ]);

        return Inertia::render('admin/seller-invites/index', [
            'invites' => $invites,
            'expiryHours' => SellerRegistrationInviteService::EXPIRY_HOURS,
        ]);
    }

    public function store(Request $request, SellerRegistrationInviteService $invites): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $invite = $invites->create(
            $request->user(),
            $validated['email'] ?? null,
            $validated['name'] ?? null,
            $validated['notes'] ?? null,
        );

        return back()->with([
            'success' => 'Seller registration link created. It expires in '.SellerRegistrationInviteService::EXPIRY_HOURS.' hours.',
            'sellerInviteUrl' => $invite->registrationUrl(),
        ]);
    }

    public function resendForSeller(
        Request $request,
        SellerProfile $seller,
        SellerRegistrationInviteService $invites,
    ): RedirectResponse {
        $seller->load('user');

        $invite = $invites->create(
            $request->user(),
            $seller->user->email,
            $seller->user->name,
            'Resent after application review.',
            $seller,
        );

        return back()->with([
            'success' => 'A new seller registration link has been created.',
            'sellerInviteUrl' => $invite->registrationUrl(),
        ]);
    }
}
