<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Holds checkout choices until Paystack (or wallet) payment succeeds.
 * No Order/Checkout rows are created while this draft is pending.
 */
class PendingCheckoutDraft
{
    public const SESSION_KEY = 'pending_checkout_draft';

    public const CACHE_TTL_SECONDS = 7200;

    /**
     * @param  array{receiver_name: string, receiver_phone: string, region: string, city: string, digital_address?: string|null, delivery_notes?: string|null}  $shipping
     * @param  array<string, array{channel: string, method_id?: int|null}>  $sellerPayments
     * @param  array<string, string>  $sellerCoupons
     */
    public static function put(
        Request $request,
        int $addressId,
        array $shipping,
        array $sellerPayments,
        array $sellerCoupons,
        string $paymentMethod,
    ): void {
        $payload = self::payload($addressId, $shipping, $sellerPayments, $sellerCoupons, $paymentMethod);
        $request->session()->put(self::SESSION_KEY, $payload);

        if ($request->user()) {
            self::putForUser($request->user(), $addressId, $shipping, $sellerPayments, $sellerCoupons, $paymentMethod);
        }
    }

    /**
     * @param  array{receiver_name: string, receiver_phone: string, region: string, city: string, digital_address?: string|null, delivery_notes?: string|null}  $shipping
     * @param  array<string, array{channel: string, method_id?: int|null}>  $sellerPayments
     * @param  array<string, string>  $sellerCoupons
     */
    public static function putForUser(
        User $user,
        int $addressId,
        array $shipping,
        array $sellerPayments,
        array $sellerCoupons,
        string $paymentMethod,
    ): void {
        Cache::put(
            self::cacheKey($user->id),
            self::payload($addressId, $shipping, $sellerPayments, $sellerCoupons, $paymentMethod),
            self::CACHE_TTL_SECONDS,
        );
    }

    /**
     * @return array{address_id: int, shipping: array, seller_payments: array, seller_coupons: array, payment_method: string, saved_at?: string}|null
     */
    public static function get(Request $request): ?array
    {
        $draft = $request->session()->get(self::SESSION_KEY);
        if (is_array($draft)) {
            return $draft;
        }

        return $request->user() ? self::getForUser($request->user()) : null;
    }

    /**
     * @return array{address_id: int, shipping: array, seller_payments: array, seller_coupons: array, payment_method: string, saved_at?: string}|null
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
        Cache::forget(self::paystackCacheKey($user->id));
        Cache::forget(self::flutterwaveCacheKey($user->id));
    }

    public static function rememberPaystack(User $user, string $reference, int $amountPesewas): void
    {
        Cache::put(self::paystackCacheKey($user->id), [
            'reference' => $reference,
            'amount_pesewas' => $amountPesewas,
        ], now()->addHours(6));
    }

    /**
     * @return array{reference: string, amount_pesewas: int}|null
     */
    public static function pendingPaystack(User $user): ?array
    {
        $pending = Cache::get(self::paystackCacheKey($user->id));

        return is_array($pending) ? $pending : null;
    }

    public static function forgetPaystack(User $user): void
    {
        Cache::forget(self::paystackCacheKey($user->id));
    }

    public static function rememberFlutterwave(User $user, string $reference, int $amountPesewas): void
    {
        Cache::put(self::flutterwaveCacheKey($user->id), [
            'reference' => $reference,
            'amount_pesewas' => $amountPesewas,
        ], now()->addHours(6));
    }

    /**
     * @return array{reference: string, amount_pesewas: int}|null
     */
    public static function pendingFlutterwave(User $user): ?array
    {
        $pending = Cache::get(self::flutterwaveCacheKey($user->id));

        return is_array($pending) ? $pending : null;
    }

    public static function forgetFlutterwave(User $user): void
    {
        Cache::forget(self::flutterwaveCacheKey($user->id));
    }

    private static function cacheKey(int $userId): string
    {
        return 'pending_checkout_draft:'.$userId;
    }

    private static function paystackCacheKey(int $userId): string
    {
        return 'paystack.pending_draft:'.$userId;
    }

    private static function flutterwaveCacheKey(int $userId): string
    {
        return 'flutterwave.pending_draft:'.$userId;
    }

    /**
     * @param  array{receiver_name: string, receiver_phone: string, region: string, city: string, digital_address?: string|null, delivery_notes?: string|null}  $shipping
     * @param  array<string, array{channel: string, method_id?: int|null}>  $sellerPayments
     * @param  array<string, string>  $sellerCoupons
     * @return array{address_id: int, shipping: array, seller_payments: array, seller_coupons: array, payment_method: string, saved_at: string}
     */
    private static function payload(
        int $addressId,
        array $shipping,
        array $sellerPayments,
        array $sellerCoupons,
        string $paymentMethod,
    ): array {
        return [
            'address_id' => $addressId,
            'shipping' => $shipping,
            'seller_payments' => $sellerPayments,
            'seller_coupons' => $sellerCoupons,
            'payment_method' => $paymentMethod,
            'saved_at' => now()->toIso8601String(),
        ];
    }
}
