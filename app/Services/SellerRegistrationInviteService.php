<?php

namespace App\Services;

use App\Enums\SellerInviteStatus;
use App\Models\SellerProfile;
use App\Models\SellerRegistrationInvite;
use App\Models\User;
use Illuminate\Support\Str;

class SellerRegistrationInviteService
{
    public const EXPIRY_HOURS = 24;

    public function create(
        User $admin,
        ?string $email = null,
        ?string $name = null,
        ?string $notes = null,
        ?SellerProfile $sellerProfile = null,
    ): SellerRegistrationInvite {
        if ($sellerProfile) {
            $this->cancelPendingInvitesForProfile($sellerProfile);
        }

        return SellerRegistrationInvite::create([
            'token' => Str::random(48),
            'email' => $email,
            'name' => $name,
            'notes' => $notes,
            'created_by' => $admin->id,
            'seller_profile_id' => $sellerProfile?->id,
            'expires_at' => now()->addHours(self::EXPIRY_HOURS),
            'status' => SellerInviteStatus::Pending,
        ]);
    }

    public function findValidByToken(string $token): ?SellerRegistrationInvite
    {
        $invite = SellerRegistrationInvite::where('token', $token)->first();

        if (! $invite) {
            return null;
        }

        if ($invite->status === SellerInviteStatus::Pending && $invite->expires_at->isPast()) {
            $invite->update(['status' => SellerInviteStatus::Expired]);

            return null;
        }

        return $invite->isValid() ? $invite : null;
    }

    public function markUsed(SellerRegistrationInvite $invite, SellerProfile $sellerProfile): void
    {
        $invite->update([
            'status' => SellerInviteStatus::Used,
            'used_at' => now(),
            'seller_profile_id' => $sellerProfile->id,
        ]);
    }

    public function cancelPendingInvitesForProfile(SellerProfile $sellerProfile): void
    {
        SellerRegistrationInvite::query()
            ->where('seller_profile_id', $sellerProfile->id)
            ->where('status', SellerInviteStatus::Pending)
            ->update(['status' => SellerInviteStatus::Cancelled]);
    }
}
