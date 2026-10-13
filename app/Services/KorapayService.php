<?php

namespace App\Services;

use App\Contracts\HostedPaymentGateway;
use App\Models\KorapayPayment;
use App\Models\User;
use App\Support\PaymentReference;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Korapay collections (checkout + wallet top-up) through Kora's hosted checkout, in GHS.
 */
class KorapayService implements HostedPaymentGateway
{
    private string $baseUrl = 'https://api.korapay.com/merchant/api/v1';

    public function name(): string
    {
        return 'korapay';
    }

    public function isConfigured(): bool
    {
        return $this->secretKey() !== '' && $this->publicKey() !== '';
    }

    public function isAvailable(): bool
    {
        return $this->isConfigured() && ! PlatformSettings::korapayPaymentsLocked();
    }

    public function unavailableMessage(): string
    {
        if (PlatformSettings::korapayPaymentsLocked()) {
            return 'Korapay payment is temporarily disabled. Please use another payment option.';
        }

        return 'Korapay is not available right now. Please use another payment option.';
    }

    public function publicKey(): string
    {
        return PlatformSettings::resolvedKorapayPublicKey();
    }

    private function secretKey(): string
    {
        return PlatformSettings::resolvedKorapaySecretKey();
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function probeCredentials(?string $secretKey = null): array
    {
        $secret = trim((string) ($secretKey ?? $this->secretKey()), " \t\n\r\0\x0B\"'");
        if ($secret === '') {
            return [
                'ok' => false,
                'message' => 'No Korapay secret key is set. Copy it from Korapay → Settings → API Configuration.',
            ];
        }

        $response = Http::withToken($secret)->acceptJson()->timeout(20)->get("{$this->baseUrl}/balances");
        if ($this->korapayOk($response)) {
            $mode = PlatformSettings::isKorapayTestKey($secret) ? 'test' : 'live';
            $message = 'Korapay accepted these '.$mode.' keys.';
            $message .= PlatformSettings::korapayPaymentsLocked()
                ? ' Turn Korapay payments on so buyers can pay.'
                : ' Wallet deposits and checkout can start.';

            return ['ok' => true, 'message' => $message];
        }

        return ['ok' => false, 'message' => $this->errorMessage($response) ?: 'Korapay rejected these keys.'];
    }

    public function rechargeFeePayload(): array
    {
        return PlatformSettings::paystackFeePayload();
    }

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

    public function initializePayment(
        string $email,
        float $amountGhs,
        string $reference,
        string $customerName,
        array $meta = [],
        ?string $redirectUrl = null,
        string $title = 'CityShop',
        ?string $customerPhone = null,
    ): array {
        if (! $this->isAvailable()) {
            throw new \RuntimeException($this->unavailableMessage());
        }

        $amount = round(max(0, $amountGhs), 2);
        if ($amount < 1) {
            throw new \RuntimeException('Amount is too small to start payment.');
        }

        $userId = (int) ($meta['user_id'] ?? $meta['buyer_id'] ?? 0);
        KorapayPayment::query()->updateOrCreate(
            ['reference' => $reference],
            [
                'user_id' => $userId > 0 ? $userId : null,
                'amount' => $amount,
                'meta' => array_merge($meta, ['gateway' => 'korapay']),
                'status' => 'pending',
            ],
        );

        $payload = [
            'amount' => $amount,
            'currency' => 'GHS',
            'reference' => $reference,
            'redirect_url' => $redirectUrl ?: url('/api/v1/flutterwave/mobile-return'),
            'notification_url' => url('/webhooks/korapay'),
            'narration' => $title,
            'channels' => ['mobile_money', 'card'],
            'default_channel' => 'mobile_money',
            'merchant_bears_cost' => true,
            'customer' => [
                'email' => $email,
                'name' => $customerName !== '' ? $customerName : 'CityShop customer',
            ],
        ];

        $response = Http::withToken($this->secretKey())
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post("{$this->baseUrl}/charges/initialize", $payload);

        if (! $this->korapayOk($response)) {
            Log::error('Korapay initialize failed', [
                'http' => $response->status(),
                'message' => $response->json('message'),
                'reference' => $reference,
            ]);

            throw new \RuntimeException($this->errorMessage($response) ?: 'Could not start Korapay payment.');
        }

        $link = (string) $response->json('data.checkout_url', '');
        if ($link === '') {
            throw new \RuntimeException('Korapay did not return a payment link.');
        }

        return [
            'authorization_url' => $link,
            'reference' => $reference,
            'email' => $email,
        ];
    }

    public function initializeWalletTopUp(
        User $user,
        float $creditGhs,
        string $method,
        string $callbackUrl,
        ?string $referencePrefix = null,
        array $extraMetadata = [],
    ): array {
        $quote = $this->rechargeQuote($creditGhs, $method);
        $reference = PaymentReference::recharge();
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
            ], $extraMetadata),
            $callbackUrl,
            'CityShop wallet top-up',
            (string) $user->mobile,
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
     * Charge details from Korapay, with our stored metadata under "meta".
     *
     * @return array<string, mixed>
     */
    public function verifyByReference(string $reference): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new \RuntimeException('Payment reference is required.');
        }

        $response = Http::withToken($this->secretKey())
            ->acceptJson()
            ->timeout(30)
            ->get("{$this->baseUrl}/charges/".rawurlencode($reference));

        if (! $this->korapayOk($response)) {
            Log::error('Korapay verify failed', [
                'http' => $response->status(),
                'message' => $response->json('message'),
                'reference' => $reference,
            ]);

            throw new \RuntimeException($this->errorMessage($response) ?: 'Payment verification failed.');
        }

        $data = $response->json('data');
        if (! is_array($data)) {
            throw new \RuntimeException('Payment verification failed.');
        }

        $record = KorapayPayment::query()->where('reference', $reference)->first();
        if ($record && $this->isSuccessful($data) && $record->status !== 'success') {
            $record->update(['status' => 'success']);
        }

        $data['tx_ref'] = $reference;
        $data['meta'] = $record?->meta ?? [];

        return $data;
    }

    public function paidAmountGhs(array $data): float
    {
        return round((float) ($data['amount'] ?? 0), 2);
    }

    public function isSuccessful(array $data): bool
    {
        return strtolower((string) ($data['status'] ?? '')) === 'success';
    }

    public function normalizeMeta(array $data): array
    {
        $meta = $data['meta'] ?? [];

        return is_array($meta) ? $meta : [];
    }

    private function korapayOk(Response $response): bool
    {
        $status = $response->json('status');

        return $response->successful() && ($status === true || $status === 'true');
    }

    private function errorMessage(Response $response): string
    {
        $message = trim((string) $response->json('message', ''));
        if ($message !== '') {
            return $message;
        }

        return $response->status() === 401 ? 'Invalid Korapay secret key.' : '';
    }
}
