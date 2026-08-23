<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(?string $phone, string $message): bool
    {
        return (bool) ($this->sendDetailed($phone, $message)['ok'] ?? false);
    }

    /**
     * @return array{ok: bool, selected: string, delivered_via: string|null, failover_used: bool, error: string|null}
     */
    public function sendDetailed(?string $phone, string $message): array
    {
        $selected = PlatformSettings::smsDriver();

        if (! $phone || trim($message) === '') {
            return [
                'ok' => false,
                'selected' => $selected,
                'delivered_via' => null,
                'failover_used' => false,
                'error' => 'Missing phone or message.',
            ];
        }

        $msisdn = $this->normalizeGhanaMsisdn($phone);
        if (! $msisdn) {
            Log::warning('SMS skipped: invalid Ghana number.', ['phone' => $phone]);

            return [
                'ok' => false,
                'selected' => $selected,
                'delivered_via' => null,
                'failover_used' => false,
                'error' => 'Invalid Ghana number.',
            ];
        }

        $ok = $this->sendViaDriver($selected, $msisdn, $message);
        if ($ok) {
            Log::info('SMS delivered via selected provider.', [
                'phone' => $msisdn,
                'provider' => $selected,
            ]);

            return [
                'ok' => true,
                'selected' => $selected,
                'delivered_via' => $selected,
                'failover_used' => false,
                'error' => null,
            ];
        }

        if (PlatformSettings::smsFailoverEnabled()) {
            $fallback = $selected === 'txtconnect' ? 'formula_dc' : 'txtconnect';
            Log::warning('SMS primary provider failed, trying failover.', [
                'phone' => $msisdn,
                'selected' => $selected,
                'failover' => $fallback,
            ]);

            $ok = $this->sendViaDriver($fallback, $msisdn, $message);
            if ($ok) {
                Log::warning('SMS delivered via failover — selected provider did not send.', [
                    'phone' => $msisdn,
                    'selected' => $selected,
                    'delivered_via' => $fallback,
                ]);

                return [
                    'ok' => true,
                    'selected' => $selected,
                    'delivered_via' => $fallback,
                    'failover_used' => true,
                    'error' => null,
                ];
            }
        }

        return [
            'ok' => false,
            'selected' => $selected,
            'delivered_via' => null,
            'failover_used' => false,
            'error' => 'All configured SMS providers failed.',
        ];
    }

    private function sendViaDriver(string $driver, string $msisdn, string $message): bool
    {
        return match ($driver) {
            'formula_dc', 'formula' => $this->sendViaFormulaDc($msisdn, $message),
            'txtconnect', 'txt_connect' => $this->sendViaTxtConnect($msisdn, $message),
            'hubtel' => $this->sendViaHubtel($msisdn, $message),
            default => tap(true, fn () => Log::channel('single')->info("SMS to {$msisdn}: {$message}")),
        };
    }

    /**
     * Formula DC requires 233 + 9 digits, no + prefix.
     */
    public function normalizeGhanaMsisdn(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '00233')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '233'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '233'.$digits;
        }

        return null;
    }

    private function sendViaFormulaDc(string $msisdn, string $message): bool
    {
        $apiKey = (string) config('services.sms.formula_dc_api_key', '');
        $sender = (string) config('services.sms.formula_dc_sender', 'Cityshop');
        $baseUrl = rtrim((string) config('services.sms.formula_dc_base_url', 'https://api.formula-dc.com/api/v1/external'), '/');
        $testMode = (bool) config('services.sms.formula_dc_test_mode', false);

        if ($apiKey === '') {
            Log::warning('Formula DC SMS not configured, logging instead.', ['phone' => $msisdn, 'message' => $message]);

            return false;
        }

        $headers = [
            'Authorization' => 'Bearer '.$apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        if ($testMode) {
            $headers['x-test-mode'] = 'true';
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(20)
                ->post("{$baseUrl}/sms/send", [
                    'to' => $msisdn,
                    'message' => $message,
                    'sender_id' => $sender,
                ]);
        } catch (\Throwable $e) {
            Log::error('Formula DC SMS request failed', [
                'phone' => $msisdn,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful() || $response->json('success') === false) {
            Log::error('Formula DC SMS send failed', [
                'phone' => $msisdn,
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return false;
        }

        Log::info('Formula DC SMS sent', [
            'phone' => $msisdn,
            'message_id' => $response->json('data.message_id'),
            'test_mode' => $testMode,
        ]);

        return true;
    }

    private function sendViaTxtConnect(string $msisdn, string $message): bool
    {
        $apiKey = (string) config('services.sms.txtconnect_api_key', '');
        $sender = (string) config('services.sms.txtconnect_sender', 'CityShop');
        $baseUrl = rtrim((string) config('services.sms.txtconnect_base_url', 'https://api.txtconnect.net/dev/api'), '/');

        if ($apiKey === '') {
            Log::warning('TxtConnect SMS not configured, logging instead.', ['phone' => $msisdn, 'message' => $message]);

            return false;
        }

        $unicode = (bool) preg_match('/[^\x00-\x7F]/', $message);

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout(20)
                ->post("{$baseUrl}/sms/send", [
                    'to' => $msisdn,
                    'from' => $sender,
                    'unicode' => $unicode,
                    'sms' => $message,
                ]);
        } catch (\Throwable $e) {
            Log::error('TxtConnect SMS request failed', [
                'phone' => $msisdn,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $body = $response->json();
        $inError = is_array($body) ? (bool) data_get($body, 'data.in_error', false) : true;
        $statusCode = is_array($body) ? (string) data_get($body, 'data.status_code', '') : '';

        if (! $response->successful() || $inError || ($statusCode !== '' && $statusCode !== '000')) {
            Log::error('TxtConnect SMS send failed', [
                'phone' => $msisdn,
                'status' => $response->status(),
                'body' => $body ?? $response->body(),
            ]);

            return false;
        }

        Log::info('TxtConnect SMS sent', [
            'phone' => $msisdn,
            'message_id' => data_get($body, 'messageId'),
        ]);

        return true;
    }

    private function sendViaHubtel(string $msisdn, string $message): bool
    {
        $clientId = config('services.sms.hubtel_client_id');
        $clientSecret = config('services.sms.hubtel_client_secret');
        $sender = config('services.sms.hubtel_sender', 'CityShop');

        if (! $clientId || ! $clientSecret) {
            Log::warning('Hubtel SMS not configured, logging instead.', ['phone' => $msisdn, 'message' => $message]);

            return false;
        }

        try {
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->timeout(20)
                ->post('https://smsc.hubtel.com/v1/messages/send', [
                    'From' => $sender,
                    'To' => $msisdn,
                    'Content' => $message,
                ]);
        } catch (\Throwable $e) {
            Log::error('Hubtel SMS request failed', [
                'phone' => $msisdn,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return $response->successful();
    }
}
