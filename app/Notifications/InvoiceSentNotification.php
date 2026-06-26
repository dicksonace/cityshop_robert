<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice->load('checkout');
        $checkout = $invoice->checkout;

        $message = (new MailMessage)
            ->subject("Invoice {$invoice->invoice_number} — {$checkout->checkout_number}")
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Thank you for your purchase on CityShop.');

        foreach ($invoice->line_items as $line) {
            $message->line(sprintf(
                '%s × %d — GH₵%s',
                $line['product_name'],
                $line['quantity'],
                number_format($line['total'], 2),
            ));
        }

        return $message
            ->line('Subtotal: GH₵'.number_format((float) $invoice->subtotal, 2))
            ->line('Total: GH₵'.number_format((float) $invoice->total, 2))
            ->line('Payment status: '.ucfirst($invoice->payment_status ?? 'pending'))
            ->line('Invoice date: '.$invoice->issued_at->format('d M Y'))
            ->action('View on CityShop', route('checkouts.show', $checkout));
    }

    public function toSms(object $notifiable): string
    {
        return "CityShop: Invoice {$this->invoice->invoice_number} for GH₵".number_format((float) $this->invoice->total, 2).'. Payment: '.($this->invoice->payment_status ?? 'pending').'.';
    }
}
