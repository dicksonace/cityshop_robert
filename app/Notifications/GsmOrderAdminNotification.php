<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\GsmOrder;
use App\Support\NotificationPrivacy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GsmOrderAdminNotification extends Notification
{
    public function __construct(public GsmOrder $order) {}

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
        $who = trim((string) ($this->order->user?->name ?? '')) ?: 'A buyer';
        $amount = NotificationPrivacy::money((float) $this->order->price_ghs);

        return (new MailMessage)
            ->subject('GSM Tools '.$this->order->reference)
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'Admin').'!')
            ->line("{$who} placed a GSM Tools order.")
            ->line('Service: '.$this->order->service_name)
            ->line('Ref: '.$this->order->reference)
            ->line("Amount: {$amount}")
            ->action('Open order', route('admin.gsm-tools.show', $this->order));
    }

    public function toSms(object $notifiable): string
    {
        $who = trim((string) ($this->order->user?->name ?? '')) ?: 'A buyer';
        $amount = NotificationPrivacy::money((float) $this->order->price_ghs);

        return "CityShop admin: {$who} placed GSM Tools {$this->order->service_name} {$this->order->reference} ({$amount}). Review in admin.";
    }
}
