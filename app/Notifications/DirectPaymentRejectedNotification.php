<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DirectPaymentRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment not confirmed — {$this->order->order_number}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('The seller could not confirm your manual payment for this order.')
            ->line("Reason: {$this->reason}")
            ->line('Please submit a valid transaction reference or payment screenshot again.')
            ->action('Resubmit payment', route('checkout.payment', $this->order->checkout_id));
    }

    public function toSms(object $notifiable): string
    {
        return "CityShop: Seller rejected payment claim for order {$this->order->order_number}. Resubmit a valid reference.";
    }
}
