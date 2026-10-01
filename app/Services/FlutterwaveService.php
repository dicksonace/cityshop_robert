<?php

namespace App\Services;

use App\Models\User;
use App\Support\PaymentReference;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Flutterwave collections (checkout + wallet top-up). Ghana GHS hosted checkout.
 * Withdrawals stay on Paystack.
 */
class FlutterwaveService
{
    private string $baseUrl = 'https://api.flutterwave.com/v3';

    public function isConfigured(): bool
    {
        return $this->secretKey() !== '' && $this->publicKey() !== '';
    }

    public function isAvailable(): bool
    {
        return $this->isConfigured() && ! PlatformSettings::flutterwavePaymentsLocked();
    }

    public function unavailableMessage(): string
    {
        if (PlatformSettings::flutterwavePaymentsLocked()) {
            return 'Flutterwave payment is temporarily disabled. Please use manual MoMo.';
        }

        return 'Flutterwave is not available right now. Please use manual MoMo.';
    }

    public function publicKey(): string
    {
        return PlatformSettings::resolvedFlutterwavePublicKey();
    }

    private function secretKey(): string
    {
        return PlatformSettings::resolvedFlutterwaveSecretKey();
    }

    private function webhookHash(): string
    {
        return PlatformSettings::resolvedFlutterwaveWebhookHash();
    }

    /**
     * Ask Flutterwave if this secret key can take Ghana (GHS) payments.
     *
     * @return array{ok: bool, message: string}
     */
    public function probeCredentials(?string $secretKey = null): array
    {
        $secret = trim((string) ($secretKey ?? $this->secretKey()), " \t\n\r\0\x0B\"'");
        if ($secret === '') {
            return [
                'ok' => false,
                'message' => 'No Flutterwave secret key is set. Paste the secret from Flutterwave → Settings → API keys.',
            ];
        }

        $http = Http::withToken($secret)->acceptJson()->timeout(20);

        $banks = $http->get("{$this->baseUrl}/banks/GH");
        if ($this->flutterwaveOk($banks)) {
            return ['ok' => true, 'message' => $this->probeSuccessMessage($secret)];
        }

        $balances = $http->get("{$this->baseUrl}/balances");
        if ($this->flutterwaveOk($balances)) {
            return ['ok' => true, 'message' => $this->probeSuccessMessage($secret)];
        }

        $pay = $http->asJson()->post("{$this->baseUrl}/payments", [
            'tx_ref' => PaymentReference::recharge(),
            'amount' => 1,
            'currency' => 'GHS',
            'redirect_url' => url('/'),
            'customer' => [
                'email' => 'flutterwave-key-test@cityunlock.net',
                'name' => 'Key test',
            ],
            'customizations' => [
                'title' => 'CityUnlock key test',
            ],
        ]);
        if ($this->flutterwaveOk($pay) && filled($pay->json('data.link'))) {
            return ['ok' => true, 'message' => $this->probeSuccessMessage($secret).' A GHS payment link opened successfully.'];
        }

        $message = $this->flutterwaveErrorMessage($banks)
            ?: $this->flutterwaveErrorMessage($balances)
            ?: $this->flutterwaveErrorMessage($pay)
            ?: 'Flutterwave rejected these keys.';

        if ($pay->status() === 400 && str_contains(strtolower($message), 'currency')) {
            $message = 'Keys are valid, but GHS is not enabled on this Flutterwave account. Turn on Ghana / GHS in Flutterwave settings.';
        }

        return ['ok' => false, 'message' => $message];
    }

    private function probeSuccessMessage(string $secret): string
    {
        $mode = PlatformSettings::isFlutterwaveTestKey($secret) ? 'TEST' : 'live';
        $message = 'Flutterwave accepted these '.$mode.' keys for Ghana (GHS).';
        if (PlatformSettings::flutterwavePaymentsLocked()) {
            $message .= ' Turn Flutterwave payments on above so buyers can pay.';
        } else {
            $message .= ' Wallet deposits and checkout can start.';
        }

        return $message;
    }

    private function flutterwaveOk(\Illuminate\Http\Client\Response $response): bool
    {
        $body = $response->json();

        return $response->successful() && is_array($body) && ($body['status'] ?? '') === 'success';
    }

    private function flutterwaveErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $body = $response->json();
        $message = is_array($body) ? trim((string) ($body['message'] ?? '')) : '';
        if ($message !== '') {
            return $message;
        }

        return $response->status() === 401 ? 'Invalid authorization key' : '';
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
        ?string $customerPhone = null,
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
            'customer' => $this->customerPayload($email, $customerName, $customerPhone),
            'customizations' => [
                'title' => $title,
                'description' => 'CityShop payment',
                'logo' => rtrim((string) config('app.url'), '/').'/images/branding/cityshop-mark.png',
            ],
            'meta' => $meta,
        ];

        $response = Http::withToken($this->secretKey())
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
                'gateway' => 'flutterwave',
            ], $extraMetadata),
            $callbackUrl,
            'CityShop',
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
     * @return array<string, mixed>
     */
    public function verifyByReference(string $txRef): array
    {
        $response = Http::withToken($this->secretKey())
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
        $expected = $this->webhookHash() !== '' ? $this->webhookHash() : $this->secretKey();
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

    /**
     * @return array{email: string, name: string, phone_number?: string}
     */
    private function customerPayload(string $email, string $name, ?string $phone = null): array
    {
        $customer = [
            'email' => $email,
            'name' => $name !== '' ? $name : 'CityShop customer',
        ];

        $normalized = $this->normalizeCustomerPhone($phone);
        if ($normalized !== null) {
            $customer['phone_number'] = $normalized;
        }

        return $customer;
    }

    private function normalizeCustomerPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($digits) < 9) {
            return null;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '233'.substr($digits, 1);
        }

        return $digits;
    }
}
