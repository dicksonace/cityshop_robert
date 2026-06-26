<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Checkout;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public bool $cashOnDelivery = false,
        public ?Checkout $checkout = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $checkout = $this->checkout ?? $this->order->checkout;
        $number = $checkout?->checkout_number ?? $this->order->order_number;
        $total = $checkout?->total ?? $this->order->total;

        $message = $this->cashOnDelivery
            ? 'Your cash-on-delivery order has been placed.'
            : 'Your order has been placed. Complete payment to confirm.';

        return (new MailMessage)
            ->subject("Order {$number} placed")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($message)
            ->line('Order total: GH₵'.number_format((float) $total, 2))
            ->action('View Order', $checkout ? route('checkouts.show', $checkout) : route('orders.show', $this->order));
    }

    public function toSms(object $notifiable): string
    {
        $checkout = $this->checkout ?? $this->order->checkout;
        $number = $checkout?->checkout_number ?? $this->order->order_number;
        $total = $checkout?->total ?? $this->order->total;

        return "CityShop: Order {$number} placed. Total GH₵".number_format((float) $total, 2).'.';
    }
}
