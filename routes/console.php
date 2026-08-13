<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sms:send {phone} {message}', function () {
    $phone = (string) $this->argument('phone');
    $message = trim((string) $this->argument('message'));
    $ok = app(\App\Services\SmsService::class)->send($phone, $message);
    if ($ok) {
        $this->info("SMS queued/sent to {$phone}");
        return self::SUCCESS;
    }
    $this->error("SMS failed for {$phone}. Check logs.");
    return self::FAILURE;
})->purpose('Send a test SMS via Formula DC / configured driver');

Artisan::command('mail:test {email}', function () {
    $email = (string) $this->argument('email');
    \Illuminate\Support\Facades\Mail::html(
        '<p style="font-family:Segoe UI,Arial,sans-serif;font-size:16px;color:#111827;">'
        .'CityShop SMTP is working. This mailbox is temporary until we switch to CityShop mail.'
        .'</p>',
        function ($message) use ($email) {
            $message->to($email)->subject('CityShop mail test');
        },
    );
    $this->info("Test email sent to {$email}");
})->purpose('Send a test email via the configured SMTP mailbox');

Schedule::command('orders:auto-confirm-deliveries')->hourly();

Schedule::call(function () {
    \App\Services\LiveStreamService::expireStale();
})->everyMinute();
