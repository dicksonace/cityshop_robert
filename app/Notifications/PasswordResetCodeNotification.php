<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail'];
        if (filled($notifiable->mobile ?? null)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your CityShop password reset code')
            ->markdown('mail.security-code', [
                'name' => filled($notifiable->name ?? null) ? $notifiable->name : 'there',
                'code' => $this->code,
                'purpose' => 'reset your CityShop password',
                'expiresMinutes' => 30,
            ]);
    }

    public function toSms(object $notifiable): string
    {
        return "CityShop code: {$this->code}. Expires in 30 min. Do not share.";
    }
}
