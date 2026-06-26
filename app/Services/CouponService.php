<?php

namespace App\Services;

use App\Enums\CouponType;
use App\Models\SellerCoupon;
use App\Models\SellerCouponUsage;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * @return array{coupon: SellerCoupon, discount: float}
     */
    public function validateForSeller(User $buyer, int $sellerId, string $code, float $subtotal): array
    {
        $coupon = SellerCoupon::where('seller_id', $sellerId)
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon' => 'Invalid coupon code for this seller.']);
        }

        if (! $coupon->isValidNow()) {
            throw ValidationException::withMessages(['coupon' => 'This coupon is no longer valid.']);
        }

        if ($subtotal < (float) $coupon->min_order_amount) {
            throw ValidationException::withMessages([
                'coupon' => 'Minimum order amount of GH₵'.number_format($coupon->min_order_amount, 2).' required.',
            ]);
        }

        $alreadyUsed = SellerCouponUsage::where('seller_coupon_id', $coupon->id)
            ->where('user_id', $buyer->id)
            ->exists();

        if ($alreadyUsed) {
            throw ValidationException::withMessages(['coupon' => 'You have already used this coupon.']);
        }

        $discount = $this->calculateDiscount($coupon, $subtotal);

        return ['coupon' => $coupon, 'discount' => $discount];
    }

    public function calculateDiscount(SellerCoupon $coupon, float $subtotal): float
    {
        return match ($coupon->type) {
            CouponType::Percentage => round(min($subtotal, $subtotal * ((float) $coupon->value / 100)), 2),
            CouponType::Fixed => round(min($subtotal, (float) $coupon->value), 2),
            CouponType::FreeShipping => 0,
        };
    }

    public function recordUsage(SellerCoupon $coupon, User $buyer, int $orderId): void
    {
        SellerCouponUsage::create([
            'seller_coupon_id' => $coupon->id,
            'user_id' => $buyer->id,
            'order_id' => $orderId,
        ]);

        $coupon->increment('used_count');
    }
}
