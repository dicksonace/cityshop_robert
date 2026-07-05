<?php

namespace App\Services;

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
