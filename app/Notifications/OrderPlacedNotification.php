<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Enums\PaymentStatus;
use App\Models\Checkout;
use App\Models\Order;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlacedNotification extends Notification
{
    public function __construct(
        public Order $order,
        public bool $cashOnDelivery = false,
        public ?Checkout $checkout = null,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = [];
        if (filled($notifiable->email ?? null)) {
            $channels[] = 'mail';
        }
        if (filled($notifiable->mobile ?? null)) {
            $channels[] = SmsChannel::class;
        }

        return $channels !== [] ? $channels : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $checkout = $this->checkout ?? $this->order->checkout;
        $number = $checkout?->checkout_number ?? $this->order->order_number;
        $total = $checkout?->total ?? $this->order->total;

        return (new MailMessage)
            ->subject("Order {$number} placed")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($this->buyerIntroLine())
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

    public function buyerIntroLine(): string
    {
        if ($this->cashOnDelivery) {
            return 'Your cash-on-delivery order has been placed.';
        }

        if ($this->paymentIsComplete()) {
            return 'Your order has been placed. Payment complete.';
        }

        return 'Your order has been placed.';
    }

    private function paymentIsComplete(): bool
    {
        $checkout = $this->checkout ?? $this->order->checkout;

        foreach ([$checkout?->payment_status, $this->order->payment_status] as $status) {
            $value = $status instanceof \BackedEnum ? $status->value : $status;
            if (strtolower((string) $value) === PaymentStatus::Paid->value) {
                return true;
            }
        }

        return false;
    }
}
