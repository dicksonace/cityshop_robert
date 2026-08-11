<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
        ];
    }
}
