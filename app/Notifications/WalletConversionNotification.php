<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Support\NotificationPrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletConversionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $direction,
        public float $amountGhs,
        public float $amountRmb,
        public string $reference,
        public float $availableBalance,
        public float $rmbBalance,
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
        $line = $this->direction === 'ghs_to_rmb'
            ? NotificationPrivacy::money($this->amountGhs).' → ¥'.number_format($this->amountRmb, 2)
            : '¥'.number_format($this->amountRmb, 2).' → '.NotificationPrivacy::money($this->amountGhs);

        return (new MailMessage)
            ->subject('Currency exchange completed')
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line("Exchange completed: {$line}.")
            ->line('GHS available: '.NotificationPrivacy::money($this->availableBalance))
            ->line('RMB available: ¥'.number_format($this->rmbBalance, 2))
            ->line('Ref: '.$this->reference)
            ->action('View China / RMB', url('/wallet/china-rmb'));
    }

    public function toSms(object $notifiable): string
    {
        $line = $this->direction === 'ghs_to_rmb'
            ? NotificationPrivacy::money($this->amountGhs).' to ¥'.number_format($this->amountRmb, 2)
            : '¥'.number_format($this->amountRmb, 2).' to '.NotificationPrivacy::money($this->amountGhs);

        return "CityShop: Exchange completed. {$line}. Ref {$this->reference}. GHS "
            .number_format($this->availableBalance, 2).' · RMB ¥'.number_format($this->rmbBalance, 2).'.';
    }
}
