<?php

namespace App\Models;

use App\Enums\SellerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class SellerProfile extends Model
{
    protected $fillable = [
        'user_id',
        'is_business_registered',
        'business_name',
        'store_name',
        'business_registration_number',
        'business_address',
        'tin',
        'slug',
        'status',
        'rejection_reason',
        'approved_at',
        'approved_by',
        'shop_photo',
        'form_a',
        'form_b',
        'business_certificate',
        'id_card_front',
        'id_card_back',
        'selfie_with_id',
        'store_description',
        'rating',
        'total_sales',
        'accept_marketplace_payments',
        'accept_direct_payments',
        'payment_methods_locked_at',
        'payment_methods_locked_by',
        'payment_methods_lock_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_business_registered' => 'boolean',
            'accept_marketplace_payments' => 'boolean',
            'accept_direct_payments' => 'boolean',
            'status' => SellerStatus::class,
            'approved_at' => 'datetime',
            'payment_methods_locked_at' => 'datetime',
            'rating' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SellerProfile $profile) {
            if (! $profile->slug) {
                $base = $profile->business_name ?? $profile->store_name ?? 'store';
                $profile->slug = static::generateUniqueSlug($base);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$original}-{$count}";
            $count++;
        }

        return $slug;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function storeCustomization(): HasOne
    {
        return $this->hasOne(StoreCustomization::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(SellerPaymentMethod::class);
    }

    public function paymentMethodsLockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_methods_locked_by');
    }

    public function paymentMethodsAreLocked(): bool
    {
        return $this->payment_methods_locked_at !== null;
    }

    public function isApproved(): bool
    {
        return $this->status === SellerStatus::Approved;
    }

    public function displayName(): string
    {
        return $this->business_name ?? $this->store_name ?? 'Store';
    }
}
