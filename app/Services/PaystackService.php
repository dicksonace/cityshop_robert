<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    private string $secretKey;

    private string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = trim((string) config('services.paystack.secret_key', ''), " \t\n\r\0\x0B\"'");
    }

    public function isConfigured(): bool
    {
        return $this->secretKey !== '' && trim((string) config('services.paystack.public_key', '')) !== '';
    }

    /**
     * Ghana local collection fee (MoMo + local cards). Admin-editable.
     *
     * @return array{enabled: bool, mode: string, percent: float, flat: float, tiers: list<array{min: float, max: float|null, fee: float}>}
     */
    public function rechargeFeePayload(): array
    {
        return PlatformSettings::paystackFeePayload();
    }

    /**
     * Charge the buyer enough to cover the net amount plus the admin Paystack fee.
     *
     * @return array{credit: float, fee: float, charge: float, percent: float, flat: float, mode: string}
     */
    public function rechargeQuote(float $creditGhs, string $method = 'momo'): array
    {
        $credit = round(max(0, $creditGhs), 2);
        $settings = PlatformSettings::paystackFeeSettings();
        $mode = $settings['mode'];
        $percent = $settings['percent'];
        $flat = $settings['flat'];

        if ($method === 'card_international' && $mode === 'percent') {
            $percent = (float) config('services.paystack.international_percent', 3.9);
            $flat = (float) config('services.paystack.international_flat', 0.20);
        }

        if ($credit <= 0 || ! $settings['enabled']) {
            return [
                'credit' => $credit,
                'fee' => 0.0,
                'charge' => $credit,
                'percent' => $percent,
                'flat' => $flat,
                'mode' => $mode,
            ];
        }

        if ($mode === 'tiers') {
            $fee = PlatformSettings::feeFromBankTiers($credit, $settings['tiers'], $flat);
            $charge = round($credit + $fee, 2);

            return [
                'credit' => $credit,
                'fee' => round($fee, 2),
                'charge' => $charge,
                'percent' => 0.0,
                'flat' => round($fee, 2),
                'mode' => $mode,
            ];
        }

        if ($mode === 'flat') {
            $fee = max(0, round($flat, 2));
            $charge = round($credit + $fee, 2);

            return [
                'credit' => $credit,
                'fee' => $fee,
                'charge' => $charge,
                'percent' => 0.0,
                'flat' => $fee,
                'mode' => $mode,
            ];
        }

        $rate = max(0, $percent) / 100;
        $flat = max(0, round($flat, 2));
        $charge = $rate >= 1
            ? round($credit + $flat, 2)
            : round(($credit + $flat) / (1 - $rate), 2);

        return [
            'credit' => $credit,
            'fee' => round($charge - $credit, 2),
            'charge' => $charge,
            'percent' => $percent,
            'flat' => $flat,
            'mode' => 'percent',
        ];
    }

    public function paidCoversCheckout(float $paidGhs, float $orderTotalGhs): bool
    {
        $quote = $this->rechargeQuote($orderTotalGhs);

        return $this->amountsMatch($paidGhs, $orderTotalGhs)
            || $this->amountsMatch($paidGhs, $quote['charge']);
    }

    /**
     * Wallet credit after a verified Paystack top-up (legacy charges credit the paid amount).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function topUpCreditFromMetadata(array $metadata, float $paidGhs): float
    {
        if (isset($metadata['wallet_credit']) && is_numeric($metadata['wallet_credit'])) {
            $credit = round((float) $metadata['wallet_credit'], 2);
            if ($credit > 0 && $credit <= $paidGhs + 0.05) {
                return $credit;
            }
        }

        return round($paidGhs, 2);
    }

    /**
     * Start a Paystack hosted checkout for wallet recharge (buyer, seller, or app).
     *
     * @param  array<string, mixed>  $extraMetadata
     * @return array{
     *   authorization_url: string,
     *   access_code: ?string,
     *   reference: string,
     *   email: string,
     *   credit: float,
     *   fee: float,
     *   charge: float
     * }
     */
    public function initializeWalletTopUp(
        User $user,
        float $creditGhs,
        string $method,
        string $callbackUrl,
        string $referencePrefix = 'TOP',
        array $extraMetadata = [],
    ): array {
        if (! $this->isConfigured()) {
            throw new \RuntimeException('Online top-up is not available. Contact support.');
        }

        $quote = $this->rechargeQuote($creditGhs, $method);
        $reference = rtrim($referencePrefix, '-').'-'.strtoupper(uniqid());
        $email = $user->billingEmail();

        $data = $this->initializeTransaction(
            $email,
            $quote['charge'],
            $reference,
            array_merge([
                'type' => 'wallet_topup',
                'user_id' => $user->id,
                'method' => $method,
                'wallet_credit' => $quote['credit'],
                'paystack_fee' => $quote['fee'],
                'expected_amount' => $quote['charge'],
            ], $extraMetadata),
            $callbackUrl,
        );

        return [
            'authorization_url' => (string) $data['authorization_url'],
            'access_code' => isset($data['access_code']) ? (string) $data['access_code'] : null,
            'reference' => (string) ($data['reference'] ?? $reference),
            'email' => $email,
            'credit' => $quote['credit'],
            'fee' => $quote['fee'],
            'charge' => $quote['charge'],
        ];
    }

    public function initializeTransaction(string $email, float $amountGhs, string $reference, array $metadata = [], ?string $callbackUrl = null): array
    {
        $email = $this->paystackEmail($email);
        $amountPesewas = (int) round(max(0, $amountGhs) * 100);
        if ($amountPesewas < 100) {
            throw new \RuntimeException('Amount is too small to start payment.');
        }

        $callback = $this->secureCallbackUrl($callbackUrl ?? route('checkout.callback'));
        $payload = [
            'email' => $email,
            'amount' => $amountPesewas,
            'currency' => 'GHS',
            'reference' => $reference,
            'callback_url' => $callback,
            'metadata' => $metadata,
        ];

        $response = Http::withToken($this->secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post("{$this->baseUrl}/transaction/initialize", $payload);

        $body = $response->json();
        if (! is_array($body)) {
            $body = [];
        }

        if (! $response->successful() || ($body['status'] ?? false) !== true) {
            Log::error('Paystack initialize failed', [
                'http' => $response->status(),
                'message' => $body['message'] ?? null,
                'email' => $email,
                'amount' => $amountPesewas,
                'reference' => $reference,
                'callback_url' => $callback,
            ]);

            throw new \RuntimeException((string) ($body['message'] ?? 'Payment initialization failed.'));
        }

        $data = $body['data'] ?? null;
        if (! is_array($data) || empty($data['authorization_url'])) {
            Log::error('Paystack initialize missing authorization_url', [
                'reference' => $reference,
                'keys' => is_array($data) ? array_keys($data) : null,
            ]);

            throw new \RuntimeException('Payment initialization failed.');
        }

        return $data;
    }

    private function paystackEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && ! str_ends_with($email, '.local')) {
            return $email;
        }

        $digits = preg_replace('/\D+/', '', $email) ?: 'cityshop';

        return $digits.'@pay.cityunlock.net';
    }

    private function secureCallbackUrl(string $url): string
    {
        $url = trim($url);
        if (str_starts_with($url, 'http://')) {
            return 'https://'.substr($url, strlen('http://'));
        }

        return $url;
    }

    public function verifyTransaction(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        if (! $response->successful()) {
            throw new \RuntimeException('Payment verification failed.');
        }

        return $response->json('data');
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = config('services.paystack.webhook_secret', $this->secretKey);

        if (! $signature || ! $secret) {
            return false;
        }

        return hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    public function paidAmountGhs(array $transactionData): float
    {
        return round(((int) ($transactionData['amount'] ?? 0)) / 100, 2);
    }

    public function amountsMatch(float $paidGhs, float $expectedGhs, float $tolerance = 0.01): bool
    {
        return abs(round($paidGhs, 2) - round($expectedGhs, 2)) <= $tolerance;
    }

    public function mobileMoneyBankCode(string $network): string
    {
        return match (strtolower($network)) {
            'mtn' => 'MTN',
            'telecel', 'vodafone', 'vod' => 'VOD',
            'airteltigo', 'atl', 'airtel', 'tigo' => 'ATL',
            default => strtoupper($network),
        };
    }

    public function normalizeGhanaPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '233') && strlen($digits) >= 12) {
            return '0'.substr($digits, 3);
        }

        return $digits;
    }

    public function createMobileMoneyRecipient(string $name, string $phone, string $network): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transferrecipient", [
                'type' => 'mobile_money',
                'name' => $name,
                'account_number' => $this->normalizeGhanaPhone($phone),
                'bank_code' => $this->mobileMoneyBankCode($network),
                'currency' => 'GHS',
            ]);

        if (! $response->successful()) {
            Log::error('Paystack recipient creation failed', ['body' => $response->json()]);
            throw new \RuntimeException($response->json('message') ?? 'Could not create Paystack payout recipient.');
        }

        return $response->json('data');
    }

    public function createBankRecipient(string $name, string $accountNumber, string $bankSlug): array
    {
        $bankCode = $this->resolveGhanaBankCode($bankSlug);

        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transferrecipient", [
                'type' => 'ghipss',
                'name' => $name,
                'account_number' => preg_replace('/\D+/', '', $accountNumber) ?: $accountNumber,
                'bank_code' => $bankCode,
                'currency' => 'GHS',
            ]);

        if (! $response->successful()) {
            Log::error('Paystack bank recipient creation failed', ['body' => $response->json(), 'bank' => $bankSlug]);
            throw new \RuntimeException($response->json('message') ?? 'Could not create Paystack bank recipient.');
        }

        return $response->json('data');
    }

    /**
     * Map CityShop bank slug → Paystack Ghana bank code (from List Banks).
     */
    public function resolveGhanaBankCode(string $bankSlug): string
    {
        $banks = $this->listGhanaBanks();
        $label = \App\Support\GhanaBanks::label($bankSlug);
        $needle = $this->normalizeBankName($label);
        $slugNeedle = $this->normalizeBankName(str_replace('_', ' ', $bankSlug));

        foreach ($banks as $bank) {
            $name = $this->normalizeBankName((string) ($bank['name'] ?? ''));
            $code = (string) ($bank['code'] ?? '');
            if ($code === '' || $name === '') {
                continue;
            }
            if ($name === $needle || $name === $slugNeedle) {
                return $code;
            }
        }

        foreach ($banks as $bank) {
            $name = $this->normalizeBankName((string) ($bank['name'] ?? ''));
            $code = (string) ($bank['code'] ?? '');
            if ($code === '' || $name === '') {
                continue;
            }
            if (str_contains($name, $needle) || str_contains($needle, $name)
                || str_contains($name, $slugNeedle) || str_contains($slugNeedle, $name)) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not match bank "'.$label.'" to a Paystack bank code.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listGhanaBanks(): array
    {
        return Cache::remember('paystack.ghana_banks.ghipss', 86400, function () {
            $response = Http::withToken($this->secretKey)
                ->get("{$this->baseUrl}/bank", [
                    'currency' => 'GHS',
                    'country' => 'ghana',
                    'type' => 'ghipss',
                    'perPage' => 100,
                ]);

            if (! $response->successful()) {
                Log::error('Paystack list banks failed', ['body' => $response->json()]);
                throw new \RuntimeException('Could not load Paystack bank list.');
            }

            $data = $response->json('data');

            return is_array($data) ? array_values($data) : [];
        });
    }

    private function normalizeBankName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9]+/', '', $name) ?? '';

        return $name;
    }

    public function initiateTransfer(string $recipientCode, float $amountGhs, string $reference, string $reason): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transfer", [
                'source' => 'balance',
                'amount' => (int) round($amountGhs * 100),
                'recipient' => $recipientCode,
                'reference' => $reference,
                'reason' => $reason,
                'currency' => 'GHS',
            ]);

        if (! $response->successful()) {
            Log::error('Paystack transfer failed', ['body' => $response->json()]);
            throw new \RuntimeException($response->json('message') ?? 'Paystack payout failed.');
        }

        return $response->json('data');
    }

    public function finalizeTransfer(string $transferCode, string $otp): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transfer/finalize_transfer", [
                'transfer_code' => $transferCode,
                'otp' => $otp,
            ]);

        if (! $response->successful()) {
            Log::error('Paystack transfer finalize failed', ['body' => $response->json()]);
            throw new \RuntimeException($response->json('message') ?? 'OTP confirmation failed.');
        }

        return $response->json('data');
    }

    public function verifyTransfer(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transfer/verify/{$reference}");

        if (! $response->successful()) {
            throw new \RuntimeException('Transfer verification failed.');
        }

        return $response->json('data');
    }
}
