<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SellerInviteStatus;
use App\Http\Controllers\Controller;
use App\Models\SellerProfile;
use App\Models\SellerRegistrationInvite;
use App\Services\SellerRegistrationInviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerInviteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $invites = SellerRegistrationInvite::with(['creator:id,name', 'sellerProfile.user:id,name,email'])
            ->latest()
            ->paginate(min(max((int) $request->integer('per_page', 20), 1), 50));

        return response()->json([
            'data' => $invites->getCollection()->map(fn (SellerRegistrationInvite $invite) => $this->serialize($invite))->values(),
            'meta' => AdminJson::meta($invites),
            'expiry_hours' => SellerRegistrationInviteService::EXPIRY_HOURS,
        ]);
    }

    public function store(Request $request, SellerRegistrationInviteService $invites): JsonResponse
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

        return response()->json([
            'message' => 'Seller registration link created. It expires in '.SellerRegistrationInviteService::EXPIRY_HOURS.' hours.',
            'data' => $this->serialize($invite->load(['creator:id,name', 'sellerProfile.user:id,name,email'])),
            'registration_url' => $invite->registrationUrl(),
        ], 201);
    }

    public function resendForSeller(
        Request $request,
        SellerProfile $seller,
        SellerRegistrationInviteService $invites,
    ): JsonResponse {
        $seller->load('user');
        $invite = $invites->create(
            $request->user(),
            $seller->user->email,
            $seller->user->name,
            'Resent after application review.',
            $seller,
        );

        return response()->json([
            'message' => 'A new seller registration link has been created.',
            'data' => $this->serialize($invite),
            'registration_url' => $invite->registrationUrl(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SellerRegistrationInvite $invite): array
    {
        return [
            'id' => $invite->id,
            'email' => $invite->email,
            'name' => $invite->name,
            'notes' => $invite->notes,
            'status' => $invite->status?->value,
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
        ];
    }
}
