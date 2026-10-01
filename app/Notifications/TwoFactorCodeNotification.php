<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification
{
    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your CityUnlock sign-in code')
            ->markdown('mail.security-code', [
                'name' => filled($notifiable->name ?? null) ? $notifiable->name : 'there',
                'code' => $this->code,
                'purpose' => 'sign in to CityUnlock',
                'expiresMinutes' => 10,
            ]);
    }
}
