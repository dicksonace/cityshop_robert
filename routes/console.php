<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('sms:check {--send-to= : Send a real test SMS to this Ghana number}', function () {
    $driver = \App\Services\PlatformSettings::smsDriver();
    $failover = \App\Services\PlatformSettings::smsFailoverEnabled();
    $formulaKey = filled(config('services.sms.formula_dc_api_key'));
    $txtKey = filled(config('services.sms.txtconnect_api_key'));

    $this->line('Active SMS driver: '.$driver);
    $this->line('Failover enabled: '.($failover ? 'yes' : 'no'));
    $this->line('Formula DC key: '.($formulaKey ? 'saved' : 'MISSING'));
    $this->line('TxtConnect key: '.($txtKey ? 'saved' : 'MISSING'));
    $this->line('Formula sender: '.config('services.sms.formula_dc_sender', 'Cityshop'));
    $this->line('TxtConnect sender: '.config('services.sms.txtconnect_sender', 'CityShop'));

    $sendTo = trim((string) $this->option('send-to'));
    if ($sendTo === '') {
        $this->warn('No test SMS sent. Run: php artisan sms:check --send-to=0XXXXXXXXX');
        return self::SUCCESS;
    }

    $message = 'CityShop SMS test at '.now()->format('Y-m-d H:i');
    $ok = app(\App\Services\SmsService::class)->send($sendTo, $message);
    if ($ok) {
        $this->info("Test SMS sent to {$sendTo} via {$driver}.");
        return self::SUCCESS;
    }

    $this->error("Test SMS failed for {$sendTo}. Check storage/logs/laravel.log");
    return self::FAILURE;
})->purpose('Show SMS platform config and optionally send a test text');

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
})->purpose('Send a test SMS via the active SMS platform');

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

Schedule::command('withdrawals:reconcile-paystack')->everyFiveMinutes();

Schedule::call(function () {
    \App\Services\StatusService::pruneExpired();
})->hourly();

Schedule::call(function () {
    \App\Services\LiveStreamService::expireStale();
})->everyMinute();
