<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Withdrawal;
use App\Support\NotificationPrivacy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalRequestedNotification extends Notification
{
    public function __construct(
        public Withdrawal $withdrawal,
        public ?float $availableBalance = null,
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
        $balance = $this->availableBalance;
        if ($balance === null) {
            $balance = (float) ($notifiable->wallet?->available_balance
                ?? \App\Models\Wallet::query()->where('user_id', $notifiable->id)->value('available_balance')
                ?? 0);
        }

        $ghs = number_format($balance, 2, '.', '');

        return "CityShop: Your withdrawal of {$amount} to {$destination} has been requested.\nAvailable Balance: GHS {$ghs}\nDate: {$when}";
    }
}
