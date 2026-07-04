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
}
