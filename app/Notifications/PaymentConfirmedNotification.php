<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public ?OrderItem $orderItem = null,
        public bool $cashOnDelivery = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->orderItem) {
            return (new MailMessage)
                ->subject('New order received')
                ->greeting('Hello '.$notifiable->name.'!')
                ->line("You have a new order: {$this->orderItem->product_name}")
                ->line("Order: {$this->order->order_number}")
                ->action('View Orders', route('seller.orders.index'));
        }

        $line = $this->cashOnDelivery
            ? 'Your cash-on-delivery order is confirmed.'
            : 'Payment confirmed! Your order is being processed.';

        return (new MailMessage)
            ->subject("Payment confirmed — {$this->order->order_number}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($line)
            ->action('Track Order', route('orders.show', $this->order));
    }

    public function toSms(object $notifiable): string
    {
        if ($this->orderItem) {
            return "CityShop: New order {$this->order->order_number} for {$this->orderItem->product_name}.";
        }

        return "CityShop: Payment confirmed for order {$this->order->order_number}.";
    }
}
