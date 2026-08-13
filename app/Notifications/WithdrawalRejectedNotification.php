<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Withdrawal;
use App\Support\NotificationPrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalRejectedNotification extends Notification implements ShouldQueue
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
        $reason = trim((string) ($this->withdrawal->rejection_reason ?: $this->withdrawal->failure_reason));

        $message = (new MailMessage)
            ->subject("Withdrawal not completed: {$amount}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').',')
            ->line("Your CityShop withdrawal of {$amount} was not completed.")
            ->line('The funds have been returned to your wallet.');

        if ($reason !== '') {
            $message->line('Reason: '.$reason);
        }

        return $message->action('View wallet', url('/wallet'));
    }

    public function toSms(object $notifiable): string
    {
        return 'CityShop: Your withdrawal of '.NotificationPrivacy::money((float) $this->withdrawal->amount)
            .' was not completed. Funds returned to your wallet.';
    }
}
