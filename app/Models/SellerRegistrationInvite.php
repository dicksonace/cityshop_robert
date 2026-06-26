<?php

namespace App\Models;

use App\Enums\SellerInviteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SellerRegistrationInvite extends Model
{
    protected $fillable = [
        'token',
        'email',
        'name',
        'notes',
        'created_by',
        'seller_profile_id',
        'expires_at',
        'used_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'status' => SellerInviteStatus::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    public function isValid(): bool
    {
        if ($this->status !== SellerInviteStatus::Pending) {
            return false;
        }

        if ($this->used_at !== null) {
            return false;
        }

        return $this->expires_at->isFuture();
    }

    public function registrationUrl(): string
    {
        return route('register.seller', ['token' => $this->token], absolute: true);
    }
}
