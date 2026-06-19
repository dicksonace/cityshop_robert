<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerRejectedNotification extends Notification implements ShouldQueue
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
            ->subject('Seller application update')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Unfortunately your seller application was not approved at this time.')
            ->line("Reason: {$this->reason}")
            ->line('You may reapply with updated documents.');
    }

    public function toSms(object $notifiable): string
    {
        return 'CityShop: Your seller application was not approved. Check your email for details.';
    }
}
