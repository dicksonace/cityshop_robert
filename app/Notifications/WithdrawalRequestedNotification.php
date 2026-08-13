<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Withdrawal;
use App\Support\NotificationPrivacy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalRequestedNotification extends Notification
{
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
        $when = NotificationPrivacy::stamp($this->withdrawal->created_at);

        return (new MailMessage)
            ->subject("Withdrawal requested: {$amount}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line("Your CityShop withdrawal of {$amount} to {$destination} has been requested.")
            ->line('Usually processed within 15 minutes and sometimes instant.')
            ->line("Date: {$when}")
            ->action('View wallet', url('/wallet'));
    }

    public function toSms(object $notifiable): string
    {
        $amount = NotificationPrivacy::money((float) $this->withdrawal->amount);
        $destination = NotificationPrivacy::maskAccount($this->withdrawal->momo_number);
        $when = NotificationPrivacy::stamp($this->withdrawal->created_at);

        return "CityShop: Your withdrawal of {$amount} to {$destination} has been requested. Usually within 15 minutes. Date: {$when}";
    }
}
