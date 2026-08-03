<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DirectCheckoutDraft
{
    public const SESSION_KEY = 'direct_checkout_draft';

    public const CACHE_TTL_SECONDS = 7200;

    /**
     * @param  array{receiver_name: string, receiver_phone: string, region: string, city: string, digital_address?: string|null, delivery_notes?: string|null}  $shipping
     * @param  array<string, array{channel: string, method_id?: int|null}>  $sellerPayments
     * @param  array<string, string>  $sellerCoupons
     */
    public static function put(Request $request, int $addressId, array $shipping, array $sellerPayments, array $sellerCoupons = []): void
    {
        $payload = self::payload($addressId, $shipping, $sellerPayments, $sellerCoupons);

        $request->session()->put(self::SESSION_KEY, $payload);

        if ($request->user()) {
            self::putForUser($request->user(), $addressId, $shipping, $sellerPayments, $sellerCoupons);
        }
    }

    /**
     * Token-auth clients (mobile) have no web session — store the draft by user id.
     *
     * @param  array{receiver_name: string, receiver_phone: string, region: string, city: string, digital_address?: string|null, delivery_notes?: string|null}  $shipping
     * @param  array<string, array{channel: string, method_id?: int|null}>  $sellerPayments
     * @param  array<string, string>  $sellerCoupons
     */
    public static function putForUser(User $user, int $addressId, array $shipping, array $sellerPayments, array $sellerCoupons = []): void
    {
        Cache::put(
            self::cacheKey($user->id),
            self::payload($addressId, $shipping, $sellerPayments, $sellerCoupons),
            self::CACHE_TTL_SECONDS,
        );
    }

    /**
     * @return array{address_id: int, shipping: array, seller_payments: array, seller_coupons: array, saved_at?: string}|null
     */
    public static function get(Request $request): ?array
    {
        $draft = $request->session()->get(self::SESSION_KEY);
        if (is_array($draft)) {
            return $draft;
        }

        if ($request->user()) {
            return self::getForUser($request->user());
        }

        return null;
    }

    /**
     * @return array{address_id: int, shipping: array, seller_payments: array, seller_coupons: array, saved_at?: string}|null
     */
    public static function getForUser(User $user): ?array
    {
        $draft = Cache::get(self::cacheKey($user->id));

        return is_array($draft) ? $draft : null;
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);

        if ($request->user()) {
            self::clearForUser($request->user());
        }
    }

    public static function clearForUser(User $user): void
    {
        Cache::forget(self::cacheKey($user->id));
    }

    private static function cacheKey(int $userId): string
    {
        return 'direct_checkout_draft:'.$userId;
    }

    /**
     * @param  array{receiver_name: string, receiver_phone: string, region: string, city: string, digital_address?: string|null, delivery_notes?: string|null}  $shipping
     * @param  array<string, array{channel: string, method_id?: int|null}>  $sellerPayments
     * @param  array<string, string>  $sellerCoupons
     * @return array{address_id: int, shipping: array, seller_payments: array, seller_coupons: array, saved_at: string}
     */
    private static function payload(int $addressId, array $shipping, array $sellerPayments, array $sellerCoupons): array
    {
        return [
            'address_id' => $addressId,
            'shipping' => $shipping,
            'seller_payments' => $sellerPayments,
            'seller_coupons' => $sellerCoupons,
            'saved_at' => now()->toIso8601String(),
        ];
    }
}
