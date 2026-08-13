<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\AppNotificationService;
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
        public bool $pendingOrder = false,
        public bool $paymentClaim = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->orderItem) {
            $awaiting = $this->isAwaitingPayment();
            $subject = AppNotificationService::sellerNewOrderTitle(
                $this->order,
                $awaiting,
                $this->cashOnDelivery,
                $this->paymentClaim,
            );

            return (new MailMessage)
                ->subject($subject)
                ->greeting('Hello '.$notifiable->name.'!')
                ->line($this->sellerIntroLine())
                ->line("Order: {$this->order->order_number}")
                ->when($this->cashOnDelivery, fn (MailMessage $mail) => $mail
                    ->line('The buyer will pay cash when you deliver. Call them to confirm, then pack and send the order.')
                )
                ->when(
                    $this->paymentClaim,
                    fn (MailMessage $mail) => $mail->line('Open the order and confirm only if you received the money.')
                )
                ->when(
                    ! $this->cashOnDelivery && ! $awaiting && ! $this->paymentClaim && $this->order->payment_channel === PaymentChannel::Direct,
                    fn (MailMessage $mail) => $mail->line('Buyer paid you directly. Confirm only if you received the money.')
                )
                ->when(
                    ! $this->cashOnDelivery && ! $awaiting && ! $this->paymentClaim && $this->order->payment_channel !== PaymentChannel::Direct,
                    fn (MailMessage $mail) => $mail->line('Buyer paid via CityShop secured. Funds settle through your seller wallet.')
                )
                ->action(
                    'View Order',
                    $this->orderItem
                        ? route('seller.orders.show', $this->orderItem->id)
                        : route('seller.orders.index'),
                );
        }

        $line = $this->cashOnDelivery
            ? 'Your cash-on-delivery order is confirmed.'
            : 'Payment confirmed! Your order is being processed.';

        return (new MailMessage)
            ->subject("Payment confirmed — {$this->order->order_number}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line($line)
            ->action('Track Order', route('checkouts.show', $this->order->checkout_id ?? $this->order));
    }

    public function toSms(object $notifiable): string
    {
        if ($this->orderItem) {
            if ($this->cashOnDelivery) {
                return "CityShop: New Order (Cash on Delivery) {$this->order->order_number} — {$this->orderItem->product_name}. Call buyer, then pack & deliver.";
            }

            if ($this->paymentClaim) {
                return "CityShop: Buyer submitted payment for {$this->order->order_number} — {$this->orderItem->product_name}. Confirm only if received.";
            }

            if ($this->isAwaitingPayment()) {
                return "CityShop: New order awaiting payment {$this->order->order_number} — {$this->orderItem->product_name}.";
            }

            if ($this->order->payment_channel === PaymentChannel::Direct) {
                return "CityShop: New order received (Paid to seller) {$this->order->order_number} — {$this->orderItem->product_name}.";
            }

            return "CityShop: Payment complete for {$this->order->order_number} — {$this->orderItem->product_name}.";
        }

        return "CityShop: Payment confirmed for order {$this->order->order_number}.";
    }

    public function sellerIntroLine(): string
    {
        $name = $this->orderItem?->product_name ?? 'an item';

        return match (true) {
            $this->cashOnDelivery => "New Order (Cash on Delivery): {$name}",
            $this->paymentClaim => "Buyer submitted payment for: {$name}",
            $this->isAwaitingPayment() => "You have a new order awaiting payment: {$name}",
            $this->order->payment_channel === PaymentChannel::Direct => "New order received (Paid to seller): {$name}",
            default => "You have a new order. Payment complete: {$name}",
        };
    }

    public function isAwaitingPayment(): bool
    {
        if ($this->cashOnDelivery || $this->paymentClaim || ! $this->pendingOrder) {
            return false;
        }

        $status = $this->order->payment_status;
        $value = $status instanceof \BackedEnum ? $status->value : $status;

        return strtolower((string) $value) !== PaymentStatus::Paid->value;
    }
}
