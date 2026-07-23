<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'mobile',
        'whatsapp',
        'password',
        'role',
        'digital_address',
        'residential_address',
        'region',
        'city',
        'ghana_card_number',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'seller_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function wishlists(): HasMany
    {
        return $this->hasMany(Wishlist::class);
    }

    public function buyerAddresses(): HasMany
    {
        return $this->hasMany(BuyerAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'buyer_id');
    }

    public function payoutMethods(): HasMany
    {
        return $this->hasMany(SellerPayoutMethod::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class, 'user_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSeller(): bool
    {
        return $this->role === UserRole::Seller;
    }

    public function isBuyer(): bool
    {
        return $this->role === UserRole::Buyer;
    }

    public function defaultRedirectRoute(): string
    {
        if ($this->isSeller()) {
            $profile = $this->sellerProfile;

            if (! $profile || $profile->status->value !== 'approved') {
                return route('seller.pending', absolute: false);
            }

            if (! $profile->storeCustomization || ! $profile->storeCustomization->isSetupComplete()) {
                return route('seller.store-setup', absolute: false);
            }

            return route('seller.dashboard', absolute: false);
        }

        return match (true) {
            $this->isAdmin() => route('admin.dashboard', absolute: false),
            default => route('home', absolute: false),
        };
    }

    public function getFullNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return "{$this->first_name} {$this->last_name}";
        }

        return $this->name;
    }
}
