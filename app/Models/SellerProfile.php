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
        'cash_on_delivery_enabled',
        'activation_fee_amount',
        'activation_prompted_at',
        'activation_paid_at',
        'activation_paid_until',
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
            'cash_on_delivery_enabled' => 'boolean',
            'status' => SellerStatus::class,
            'approved_at' => 'datetime',
            'activation_fee_amount' => 'decimal:2',
            'activation_prompted_at' => 'datetime',
            'activation_paid_at' => 'datetime',
            'activation_paid_until' => 'datetime',
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

    /** Stores without the column set keep the old always-on behaviour. */
    public function acceptsCashOnDelivery(): bool
    {
        return (bool) ($this->cash_on_delivery_enabled ?? true);
    }

    public function isApproved(): bool
    {
        return $this->status === SellerStatus::Approved;
    }

    /**
     * Store is live for buyers. Unprompted sellers stay active; after admin
     * asks for the annual fee, the store stays live only while paid_until is future.
     */
    public function isServiceActive(): bool
    {
        if ($this->activation_paid_until && $this->activation_paid_until->isFuture()) {
            return true;
        }

        $prompted = $this->activation_prompted_at !== null || (float) ($this->activation_fee_amount ?? 0) > 0;

        return ! $prompted;
    }

    public function needsActivationPayment(): bool
    {
        return $this->isApproved() && ! $this->isServiceActive();
    }

    /**
     * @return array{
     *   fee_amount: float,
     *   prompted_at: string|null,
     *   paid_until: string|null,
     *   paid_at: string|null,
     *   is_active: bool,
     *   needs_payment: bool
     * }
     */
    public function activationPayload(): array
    {
        return [
            'fee_amount' => (float) ($this->activation_fee_amount ?? 0),
            'prompted_at' => $this->activation_prompted_at?->toIso8601String(),
            'paid_until' => $this->activation_paid_until?->toIso8601String(),
            'paid_at' => $this->activation_paid_at?->toIso8601String(),
            'is_active' => $this->isServiceActive(),
            'needs_payment' => $this->needsActivationPayment(),
        ];
    }

    public function scopeServiceActive($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($inner) {
                $inner->whereNull('activation_prompted_at')
                    ->where(function ($fee) {
                        $fee->whereNull('activation_fee_amount')
                            ->orWhere('activation_fee_amount', '<=', 0);
                    });
            })->orWhere('activation_paid_until', '>', now());
        });
    }

    public function displayName(): string
    {
        return $this->business_name ?? $this->store_name ?? 'Store';
    }
}
