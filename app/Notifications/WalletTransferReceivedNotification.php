<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\User;
use App\Support\NotificationPrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletTransferReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public User $sender,
        public float $amount,
        public string $reference,
        public ?string $note = null,
    ) {}

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
        $amount = NotificationPrivacy::money($this->amount);
        $from = trim((string) $this->sender->name) ?: 'a CityShop user';

        $message = (new MailMessage)
            ->subject("You received {$amount} on CityShop")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line("{$from} sent you {$amount}.");

        if (filled($this->note)) {
            $message->line('Note: '.$this->note);
        }

        return $message
            ->line('Reference: '.$this->reference)
            ->action('View wallet', url('/wallet'));
    }

    public function toSms(object $notifiable): string
    {
        $from = trim((string) $this->sender->name) ?: 'a CityShop user';

        return 'CityShop: You received '.NotificationPrivacy::money($this->amount)
            ." from {$from}. Ref {$this->reference}.";
    }
}
