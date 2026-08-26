<?php

namespace App\Channels;

use App\Notifications\PasswordResetCodeNotification;
use App\Notifications\PaymentPinResetCodeNotification;
use App\Services\SmsService;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private SmsService $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);

        if (! $message) {
            return;
        }

        // OTP / reset codes must use the admin-selected provider only — no silent
        // fallback to Formula DC when TxtConnect is selected (and vice versa).
        $allowFailover = ! ($notification instanceof PaymentPinResetCodeNotification
            || $notification instanceof PasswordResetCodeNotification);

        $phones = method_exists($notification, 'smsRecipients')
            ? $notification->smsRecipients($notifiable)
            : [$notifiable->mobile ?? $notifiable->phone ?? null];

        $sent = [];
        foreach ($phones as $phone) {
            if (! is_string($phone) || trim($phone) === '') {
                continue;
            }

            $msisdn = $this->sms->normalizeGhanaMsisdn($phone);
            if (! $msisdn || isset($sent[$msisdn])) {
                continue;
            }

            $sent[$msisdn] = true;
            $this->sms->send($phone, $message, $allowFailover);
        }
    }
}
