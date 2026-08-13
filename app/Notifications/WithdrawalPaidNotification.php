<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Withdrawal;
use App\Support\NotificationPrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalPaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Withdrawal $withdrawal) {}

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
        $amount = NotificationPrivacy::money((float) $this->withdrawal->amount);
        $destination = NotificationPrivacy::maskAccount($this->withdrawal->momo_number);
        $network = trim((string) ($this->withdrawal->network ?? ''));

        return (new MailMessage)
            ->subject("Withdrawal paid: {$amount}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line("Your CityShop withdrawal of {$amount} has been paid.")
            ->line($network !== '' ? "Sent to {$destination} ({$network})." : "Sent to {$destination}.")
            ->action('View wallet', url('/wallet'));
    }

    public function toSms(object $notifiable): string
    {
        $amount = NotificationPrivacy::money((float) $this->withdrawal->amount);
        $destination = NotificationPrivacy::maskAccount($this->withdrawal->momo_number);

        return "CityShop: Your withdrawal of {$amount} to {$destination} has been paid.";
    }
}
