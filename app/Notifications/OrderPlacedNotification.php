<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order, public bool $cashOnDelivery = false) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->cashOnDelivery
            ? 'Your cash-on-delivery order has been placed.'
            : 'Your order has been placed. Complete payment to confirm.';

        return (new MailMessage)
            ->subject("Order {$this->order->order_number} placed")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($message)
            ->line('Order total: GH₵'.number_format($this->order->total, 2))
            ->action('View Order', route('orders.show', $this->order));
    }

    public function toSms(object $notifiable): string
    {
        return "CityShop: Order {$this->order->order_number} placed. Total GH₵".number_format($this->order->total, 2).'.';
    }
}
