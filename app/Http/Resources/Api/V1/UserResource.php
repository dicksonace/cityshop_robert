<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->loadMissing('sellerProfile');

        $profile = $this->sellerProfile;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'country' => $this->country,
            'role' => $this->role?->value,
            'region' => $this->region,
            'city' => $this->city,
            'avatar' => $this->publicAvatarUrl(),
            'has_payment_pin' => filled($this->payment_pin),
            'seller' => $profile ? [
                'store_name' => $profile->displayName(),
                'slug' => $profile->slug,
                'status' => $profile->status?->value,
                'store_setup_complete' => filled($profile->store_name) || filled($profile->business_name),
            ] : null,
        ];
    }
}
