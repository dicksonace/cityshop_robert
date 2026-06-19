<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(?string $phone, string $message): void
    {
        if (! $phone) {
            return;
        }

        $driver = config('services.sms.driver', 'log');

        match ($driver) {
            'hubtel' => $this->sendViaHubtel($phone, $message),
            default => Log::channel('single')->info("SMS to {$phone}: {$message}"),
        };
    }

    private function sendViaHubtel(string $phone, string $message): void
    {
        $clientId = config('services.sms.hubtel_client_id');
        $clientSecret = config('services.sms.hubtel_client_secret');
        $sender = config('services.sms.hubtel_sender', 'CityShop');

        if (! $clientId || ! $clientSecret) {
            Log::warning('Hubtel SMS not configured, logging instead.', compact('phone', 'message'));

            return;
        }

        Http::withBasicAuth($clientId, $clientSecret)
            ->post('https://smsc.hubtel.com/v1/messages/send', [
                'From' => $sender,
                'To' => $phone,
                'Content' => $message,
            ]);
    }
}
