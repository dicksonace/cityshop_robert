<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\GsmOrder;
use App\Support\NotificationPrivacy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GsmOrderBuyerNotification extends Notification
{
    public function __construct(
        public GsmOrder $order,
        public string $title,
        public string $message,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];
        if (filled($notifiable->mobile ?? null)) {
            $channels[] = SmsChannel::class;
        }
        if (filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = NotificationPrivacy::money((float) $this->order->price_ghs);

        return (new MailMessage)
            ->subject($this->title.' '.$this->order->reference)
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line($this->message)
            ->line('Service: '.$this->order->service_name)
            ->line('Ref: '.$this->order->reference)
            ->line("Amount: {$amount}")
            ->action('Open order', route('gsm-tools.orders.show', $this->order));
    }

    public function toSms(object $notifiable): string
    {
        $amount = NotificationPrivacy::money((float) $this->order->price_ghs);
        $detail = trim($this->message);

        return "CityShop: {$this->title}. {$this->order->service_name} {$this->order->reference} ({$amount}). {$detail}";
    }
}
