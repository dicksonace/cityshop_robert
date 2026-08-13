<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Support\NotificationPrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletFundedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public float $amount,
        public string $method,
        public string $reference,
    ) {}

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
        $amount = NotificationPrivacy::money($this->amount);
        $method = NotificationPrivacy::fundingMethod($this->method);

        return (new MailMessage)
            ->subject("{$amount} added to your CityShop wallet")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line("{$amount} has been added to your CityShop wallet via {$method}.")
            ->line('Reference: '.$this->reference)
            ->action('View wallet', url('/wallet'));
    }

    public function toSms(object $notifiable): string
    {
        return 'CityShop: '.NotificationPrivacy::money($this->amount)
            .' added to your wallet. Ref '.$this->reference.'.';
    }
}
