<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\OrderItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public OrderItem $orderItem,
        public string $status,
        public bool $refunded = false,
        public float $refundAmount = 0,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $labels = [
            'packed' => 'Your order is being packed',
            'shipped' => 'Your order is out for delivery',
            'awaiting_confirmation' => 'Your order was delivered — please confirm receipt',
            'delivered' => 'Your order is complete',
            'cancelled' => $this->isAdminCancel()
                ? 'Your order was cancelled by CityShop support'
                : 'Your order was cancelled by the seller',
        ];

        $statusLabel = $this->statusLabel();

        $mail = (new MailMessage)
            ->subject($labels[$this->status] ?? 'Order update')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line("{$this->orderItem->product_name} — status: {$statusLabel}");

        if ($this->orderItem->rejection_reason) {
            $mail->line("Reason: {$this->orderItem->rejection_reason}");
        }

        if ($this->refunded && $this->refundAmount > 0) {
            $mail->line('GH₵'.number_format($this->refundAmount, 2).' has been credited to your CityShop wallet.');
        }

        if ($this->orderItem->tracking_number) {
            $mail->line("Tracking: {$this->orderItem->courier_name} — {$this->orderItem->tracking_number}");
        }

        return $mail->action('View Order', route('orders.show', $this->orderItem->order_id));
    }

    public function toSms(object $notifiable): string
    {
        $this->orderItem->loadMissing('order');

        $statusLabel = $this->statusLabel();
        $message = "CityShop: {$this->orderItem->product_name} is now {$statusLabel}. Order {$this->orderItem->order->order_number}.";

        if ($this->refunded && $this->refundAmount > 0) {
            $message .= ' GH₵'.number_format($this->refundAmount, 2).' refunded to your wallet.';
        }

        return $message;
    }

    private function statusLabel(): string
    {
        return match ($this->status) {
            'packed' => 'being packed',
            'shipped' => 'out for delivery',
            'awaiting_confirmation' => 'delivered — confirm receipt',
            'delivered' => 'complete',
            'cancelled' => $this->isAdminCancel() ? 'cancelled by CityShop support' : 'cancelled',
            default => $this->status,
        };
    }

    private function isAdminCancel(): bool
    {
        $reason = (string) ($this->orderItem->rejection_reason ?? '');

        return str_starts_with(mb_strtolower($reason), 'admin');
    }
}
