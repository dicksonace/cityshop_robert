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

Schedule::command('orders:auto-confirm-deliveries')->hourly();

Schedule::call(function () {
    \App\Services\LiveStreamService::expireStale();
})->everyMinute();
