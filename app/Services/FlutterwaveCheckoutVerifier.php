<?php

namespace App\Services;

use App\Enums\PaymentChannel;
use App\Models\Checkout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FlutterwaveCheckoutVerifier
{
    public const CACHE_KEY_PREFIX = 'flutterwave.checkout.';

    public function __construct(private FlutterwaveService $flutterwave) {}

    public function rememberPending(Checkout $checkout, string $reference, int $amountPesewas): void
    {
        Cache::put(self::CACHE_KEY_PREFIX.$checkout->id, [
            'reference' => $reference,
            'amount_pesewas' => $amountPesewas,
        ], now()->addHours(6));
    }

    public function forgetPending(Checkout $checkout): void
    {
        Cache::forget(self::CACHE_KEY_PREFIX.$checkout->id);
    }

    /**
     * @param  array<string, mixed>|null  $flutterwaveData
     * @return array<string, mixed>
     */
    public function verifyForCheckout(Checkout $checkout, string $reference, ?array $flutterwaveData = null): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw ValidationException::withMessages([
                'reference' => 'Payment reference is required.',
            ]);
        }

        $pending = Cache::get(self::CACHE_KEY_PREFIX.$checkout->id);
        if (is_array($pending) && ! empty($pending['reference'])) {
            if (! hash_equals((string) $pending['reference'], $reference)) {
                $checkout->loadMissing('orders');
                $known = $checkout->orders
                    ->where('payment_channel', PaymentChannel::Marketplace)
                    ->pluck('payment_reference')
                    ->filter()
                    ->contains(fn ($ref) => hash_equals((string) $ref, $reference));

                if (! $known) {
                    throw ValidationException::withMessages([
                        'reference' => 'This payment reference does not match the pending checkout.',
                    ]);
                }
            }
        }

        $data = $flutterwaveData ?? $this->flutterwave->verifyByReference($reference);

        if (! $this->flutterwave->isSuccessful($data)) {
            throw ValidationException::withMessages([
                'reference' => 'Payment was not successful.',
            ]);
        }

        $meta = $this->flutterwave->normalizeMeta($data);
        $metaCheckoutId = (int) ($meta['checkout_id'] ?? 0);
        if ($metaCheckoutId !== (int) $checkout->id) {
            Log::warning('Flutterwave checkout_id mismatch', [
                'checkout_id' => $checkout->id,
                'metadata_checkout_id' => $metaCheckoutId,
                'reference' => $reference,
            ]);

            throw ValidationException::withMessages([
                'reference' => 'Payment does not belong to this checkout.',
            ]);
        }

        $currency = strtoupper((string) ($data['currency'] ?? ''));
        if ($currency !== 'GHS') {
            throw ValidationException::withMessages([
                'reference' => 'Unexpected payment currency.',
            ]);
        }

        $paid = $this->flutterwave->paidAmountGhs($data);
        $expectedMeta = isset($meta['expected_amount']) ? (float) $meta['expected_amount'] : null;
        if ($expectedMeta !== null && ! $this->flutterwave->amountsMatch($paid, $expectedMeta)) {
            throw ValidationException::withMessages([
                'reference' => 'Payment amount could not be verified.',
            ]);
        }

        $orderAmount = app(CheckoutPaymentVerifier::class)->marketplaceAmountGhs($checkout);
        $quote = $this->flutterwave->rechargeQuote($orderAmount);
        if (! $this->flutterwave->amountsMatch($paid, $quote['charge'])) {
            // Allow paid >= expected charge (customer overpay is rare but ok if close).
            if ($paid + 0.01 < $quote['charge']) {
                throw ValidationException::withMessages([
                    'reference' => 'Payment amount is less than the order total.',
                ]);
            }
        }

        $txRef = (string) ($data['tx_ref'] ?? $reference);
        if (! hash_equals($txRef, $reference) && ! hash_equals((string) ($data['flw_ref'] ?? ''), $reference)) {
            // Prefer matching tx_ref; still accept if verify_by_reference used our ref.
        }

        return $data;
    }
}
