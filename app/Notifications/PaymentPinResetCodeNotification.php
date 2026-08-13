<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentPinResetCodeNotification extends Notification
{

    public function __construct(
        public string $code,
        public string $channel = 'email',
    ) {}

    public function via(object $notifiable): array
    {
        return $this->channel === 'sms'
            ? [SmsChannel::class]
            : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your CityShop payment PIN reset code')
            ->markdown('mail.security-code', [
                'name' => filled($notifiable->name ?? null) ? $notifiable->name : 'there',
                'code' => $this->code,
                'purpose' => 'reset your CityShop payment PIN',
                'expiresMinutes' => 30,
            ]);
    }

    public function toSms(object $notifiable): string
    {
        return "CityShop PIN reset code: {$this->code}. Expires in 30 min. Do not share.";
    }
}
