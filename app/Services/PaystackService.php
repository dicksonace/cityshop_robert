<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaystackService
{
    private string $secretKey;

    private string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key', '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->secretKey) && ! empty(config('services.paystack.public_key'));
    }

    public function initializeTransaction(string $email, float $amountGhs, string $reference, array $metadata = [], ?string $callbackUrl = null): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email' => $email,
                'amount' => (int) round($amountGhs * 100),
                'currency' => 'GHS',
                'reference' => $reference,
                'callback_url' => $callbackUrl ?? route('checkout.callback'),
                'metadata' => $metadata,
            ]);

        if (! $response->successful()) {
            Log::error('Paystack initialize failed', ['body' => $response->json()]);
            throw new \RuntimeException($response->json('message') ?? 'Payment initialization failed.');
        }

        return $response->json('data');
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
