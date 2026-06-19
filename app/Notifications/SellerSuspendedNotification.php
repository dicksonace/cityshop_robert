<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your CityShop seller account has been suspended')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your seller account has been blocked by CityShop admin. Your products are no longer visible to buyers.')
            ->line("Reason: {$this->reason}")
            ->line('Contact support if you believe this was a mistake.');
    }

    public function toSms(object $notifiable): string
    {
        return 'CityShop: Your seller account has been suspended. Your products are hidden. Check your email for details.';
    }
}
