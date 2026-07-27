<?php

namespace App\Services;

use App\Enums\PaymentChannel;
use App\Models\Checkout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CheckoutPaymentVerifier
{
    public const CACHE_KEY_PREFIX = 'paystack.checkout.';

    public function __construct(private PaystackService $paystack) {}

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
     * Marketplace amount due for Paystack (GHS).
     */
    public function marketplaceAmountGhs(Checkout $checkout): float
    {
        $checkout->loadMissing('orders');

        return (float) $checkout->orders
            ->where('payment_channel', PaymentChannel::Marketplace)
            ->sum('total');
    }

    /**
     * Verify Paystack charge against this checkout. Throws on any mismatch.
     *
     * @param  array<string, mixed>|null  $paystackData  Optional pre-fetched verify payload
     * @return array<string, mixed> Paystack transaction data
     */
    public function verifyForCheckout(Checkout $checkout, string $reference, ?array $paystackData = null): array
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
                // Allow verify if orders were initialized with this reference (retry / webhook race).
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

        $data = $paystackData ?? $this->paystack->verifyTransaction($reference);

        if (($data['status'] ?? '') !== 'success') {
            throw ValidationException::withMessages([
                'reference' => 'Payment was not successful.',
            ]);
        }

        $metaCheckoutId = (int) ($data['metadata']['checkout_id'] ?? 0);
        if ($metaCheckoutId !== (int) $checkout->id) {
            Log::warning('Paystack checkout_id mismatch', [
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

        $expectedPesewas = (int) round($this->marketplaceAmountGhs($checkout) * 100);
        $paidPesewas = (int) ($data['amount'] ?? 0);

        if ($expectedPesewas <= 0 || $paidPesewas !== $expectedPesewas) {
            Log::warning('Paystack amount mismatch', [
                'checkout_id' => $checkout->id,
                'expected' => $expectedPesewas,
                'paid' => $paidPesewas,
                'reference' => $reference,
            ]);

            throw ValidationException::withMessages([
                'reference' => 'Paid amount does not match checkout total.',
            ]);
        }

        return $data;
    }
}
