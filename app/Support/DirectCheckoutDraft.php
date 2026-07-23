<?php

namespace App\Support;

use Illuminate\Http\Request;

class DirectCheckoutDraft
{
    public const SESSION_KEY = 'direct_checkout_draft';

    /**
     * @param  array{receiver_name: string, receiver_phone: string, region: string, city: string, digital_address?: string|null, delivery_notes?: string|null}  $shipping
     * @param  array<string, array{channel: string, method_id?: int|null}>  $sellerPayments
     * @param  array<string, string>  $sellerCoupons
     */
    public static function put(Request $request, int $addressId, array $shipping, array $sellerPayments, array $sellerCoupons = []): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'address_id' => $addressId,
            'shipping' => $shipping,
            'seller_payments' => $sellerPayments,
            'seller_coupons' => $sellerCoupons,
            'saved_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array{address_id: int, shipping: array, seller_payments: array, seller_coupons: array, saved_at?: string}|null
     */
    public static function get(Request $request): ?array
    {
        $draft = $request->session()->get(self::SESSION_KEY);

        return is_array($draft) ? $draft : null;
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
