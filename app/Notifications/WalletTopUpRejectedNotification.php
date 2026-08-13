<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Support\NotificationPrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletTopUpRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public float $amount) {}

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
            ->subject('Wallet top-up was not approved')
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').',')
            ->line('Your manual wallet top-up of '.NotificationPrivacy::money($this->amount).' was not approved.')
            ->line('If you still need to add funds, submit a new request with a clearer payment proof.')
            ->action('Try again', url('/wallet/manual-top-up'));
    }

    public function toSms(object $notifiable): string
    {
        return 'CityShop: Your wallet top-up of '.NotificationPrivacy::money($this->amount)
            .' was not approved. Open the app for details.';
    }
}
