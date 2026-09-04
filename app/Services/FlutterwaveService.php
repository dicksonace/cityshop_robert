<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Flutterwave collections (checkout + wallet top-up). Ghana GHS hosted checkout.
 * Withdrawals stay on Paystack.
 */
class FlutterwaveService
{
    private string $secretKey;

    private string $publicKey;

    private string $webhookHash;

    private string $baseUrl = 'https://api.flutterwave.com/v3';

    public function __construct()
    {
        $this->secretKey = trim((string) config('services.flutterwave.secret_key', ''), " \t\n\r\0\x0B\"'");
        $this->publicKey = trim((string) config('services.flutterwave.public_key', ''), " \t\n\r\0\x0B\"'");
        $this->webhookHash = trim((string) config('services.flutterwave.webhook_hash', ''), " \t\n\r\0\x0B\"'");
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && $this->publicKey !== '';
    }

    public function isAvailable(): bool
    {
        return $this->isConfigured() && ! PlatformSettings::flutterwavePaymentsLocked();
    }

    public function unavailableMessage(): string
    {
        if (PlatformSettings::flutterwavePaymentsLocked()) {
            return 'Flutterwave payment is temporarily disabled. Try Paystack or manual MoMo.';
        }

        return 'Flutterwave is not available right now. Try Paystack or manual MoMo.';
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    /**
     * Same collection fee rules as Paystack (admin Paystack Fees screen).
     *
     * @return array{enabled: bool, mode: string, percent: float, flat: float, tiers: list<array{min: float, max: float|null, fee: float}>}
     */
    public function rechargeFeePayload(): array
    {
        return PlatformSettings::paystackFeePayload();
    }

    /**
     * @return array{credit: float, fee: float, charge: float, percent: float, flat: float, mode: string}
     */
    public function rechargeQuote(float $creditGhs, string $method = 'momo'): array
    {
        return app(PaystackService::class)->rechargeQuote($creditGhs, $method);
    }

    public function topUpCreditFromMetadata(array $metadata, float $paidGhs): float
    {
        return app(PaystackService::class)->topUpCreditFromMetadata($metadata, $paidGhs);
    }

    public function amountsMatch(float $paidGhs, float $expectedGhs, float $tolerance = 0.01): bool
    {
        return abs(round($paidGhs, 2) - round($expectedGhs, 2)) <= $tolerance;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{authorization_url: string, reference: string, email: string}
     */
    public function initializePayment(
        string $email,
        float $amountGhs,
        string $reference,
        string $customerName,
        array $meta = [],
        ?string $redirectUrl = null,
        string $title = 'CityShop',
    ): array {
        if (! $this->isAvailable()) {
            throw new \RuntimeException($this->unavailableMessage());
        }

        $amount = round(max(0, $amountGhs), 2);
        if ($amount < 1) {
            throw new \RuntimeException('Amount is too small to start payment.');
        }

        $redirect = $this->secureCallbackUrl($redirectUrl ?? url('/api/v1/flutterwave/mobile-return'));

        $payload = [
            'tx_ref' => $reference,
            'amount' => $amount,
            'currency' => 'GHS',
            'redirect_url' => $redirect,
            'payment_options' => 'card,mobilemoneyghana,ussd,banktransfer',
            'customer' => [
                'email' => $email,
                'name' => $customerName !== '' ? $customerName : 'CityShop customer',
            ],
            'customizations' => [
                'title' => $title,
                'description' => 'CityShop payment',
            ],
            'meta' => $meta,
        ];

        $response = Http::withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post("{$this->baseUrl}/payments", $payload);

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful() || ($body['status'] ?? '') !== 'success') {
            Log::error('Flutterwave initialize failed', [
                'http' => $response->status(),
                'message' => $body['message'] ?? null,
                'reference' => $reference,
            ]);

            throw new \RuntimeException((string) ($body['message'] ?? 'Could not start Flutterwave payment.'));
        }

        $link = (string) ($body['data']['link'] ?? '');
        if ($link === '') {
            throw new \RuntimeException('Flutterwave did not return a payment link.');
        }

        return [
            'authorization_url' => $link,
            'reference' => $reference,
            'email' => $email,
        ];
    }

    /**
     * @param  array<string, mixed>  $extraMetadata
     * @return array{authorization_url: string, reference: string, email: string, credit: float, fee: float, charge: float}
     */
    public function initializeWalletTopUp(
        User $user,
        float $creditGhs,
        string $method,
        string $callbackUrl,
        string $referencePrefix = 'FLW-TOP',
        array $extraMetadata = [],
    ): array {
        $quote = $this->rechargeQuote($creditGhs, $method);
        $reference = rtrim($referencePrefix, '-').'-'.strtoupper(uniqid());
        $email = $user->billingEmail();

        $data = $this->initializePayment(
            $email,
            $quote['charge'],
            $reference,
            (string) $user->name,
            array_merge([
                'type' => 'wallet_topup',
                'user_id' => $user->id,
                'method' => $method,
                'wallet_credit' => $quote['credit'],
                'paystack_fee' => $quote['fee'],
                'gateway_fee' => $quote['fee'],
                'expected_amount' => $quote['charge'],
                'gateway' => 'flutterwave',
            ], $extraMetadata),
            $callbackUrl,
            'CityShop Wallet',
        );

        return [
            'authorization_url' => $data['authorization_url'],
            'reference' => $data['reference'],
            'email' => $email,
            'credit' => $quote['credit'],
            'fee' => $quote['fee'],
            'charge' => $quote['charge'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyByReference(string $txRef): array
    {
        $response = Http::withToken($this->secretKey)
            ->acceptJson()
            ->timeout(30)
            ->get("{$this->baseUrl}/transactions/verify_by_reference", [
                'tx_ref' => $txRef,
            ]);

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful() || ($body['status'] ?? '') !== 'success') {
            Log::error('Flutterwave verify failed', [
                'http' => $response->status(),
                'message' => $body['message'] ?? null,
                'tx_ref' => $txRef,
            ]);

            throw new \RuntimeException((string) ($body['message'] ?? 'Payment verification failed.'));
        }

        $data = $body['data'] ?? null;
        if (! is_array($data)) {
            throw new \RuntimeException('Payment verification failed.');
        }

        return $data;
    }

    public function paidAmountGhs(array $data): float
    {
        return round((float) ($data['amount'] ?? 0), 2);
    }

    public function isSuccessful(array $data): bool
    {
        return strtolower((string) ($data['status'] ?? '')) === 'successful';
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeMeta(array $data): array
    {
        $meta = $data['meta'] ?? [];
        if (! is_array($meta)) {
            return [];
        }

        // Flutterwave may nest custom meta under meta or flatten keys.
        $out = [];
        foreach ($meta as $key => $value) {
            if (is_string($key)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public function verifyWebhookSignature(?string $verifHash): bool
    {
        $expected = $this->webhookHash !== '' ? $this->webhookHash : $this->secretKey;
        if ($expected === '' || $verifHash === null || $verifHash === '') {
            return false;
        }

        return hash_equals($expected, $verifHash);
    }

    private function secureCallbackUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return url('/api/v1/flutterwave/mobile-return');
        }

        return $url;
    }
}
