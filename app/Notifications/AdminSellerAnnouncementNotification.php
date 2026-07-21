<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminSellerAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('CityShop: '.$this->title)
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($this->body)
            ->action('Open Seller Hub', url('/seller/dashboard'))
            ->line('Thank you for selling on CityShop.');
    }

    public function toSms(object $notifiable): string
    {
        $snippet = mb_substr($this->body, 0, 120);

        return "CityShop: {$this->title}. {$snippet}";
    }
}
