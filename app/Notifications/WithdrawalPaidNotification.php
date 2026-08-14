<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Withdrawal;
use App\Support\NotificationPrivacy;
use App\Support\PayoutNetwork;
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
        $network = PayoutNetwork::label($this->withdrawal->network);

        $when = NotificationPrivacy::stamp($this->withdrawal->processed_at ?? $this->withdrawal->created_at);

        return (new MailMessage)
            ->subject("Withdrawal paid: {$amount}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line("Your CityShop withdrawal of {$amount} has been paid.")
            ->line($network !== '' ? "Sent to {$destination} via {$network}." : "Sent to {$destination}.")
            ->line("Date: {$when}")
            ->action('View wallet', url('/wallet'));
    }

    public function toSms(object $notifiable): string
    {
        $amount = NotificationPrivacy::money((float) $this->withdrawal->amount);
        $destination = NotificationPrivacy::maskAccount($this->withdrawal->momo_number);
        $network = PayoutNetwork::label($this->withdrawal->network);
        $when = NotificationPrivacy::stamp($this->withdrawal->processed_at ?? $this->withdrawal->created_at);
        $via = $network !== '' ? " via {$network}" : '';

        return "CityShop: Your withdrawal of {$amount} to {$destination}{$via} has been paid. Date: {$when}";
    }
}
