<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\SellerProfile;
use App\Support\NotificationPrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerActivationPaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SellerProfile $profile,
        public float $availableBalance = 0,
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
        $amount = NotificationPrivacy::money((float) $this->profile->activation_fee_amount);
        $until = NotificationPrivacy::stamp($this->profile->activation_paid_until);
        $ghs = number_format($this->availableBalance, 2, '.', '');

        return (new MailMessage)
            ->subject('Seller service fee paid')
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line("Your {$amount} seller service fee has been paid.")
            ->line("Store active until: {$until}")
            ->line("Available Balance: GHS {$ghs}")
            ->action('Open seller hub', route('seller.dashboard'));
    }

    public function toSms(object $notifiable): string
    {
        $amount = NotificationPrivacy::money((float) $this->profile->activation_fee_amount);
        $until = NotificationPrivacy::stamp($this->profile->activation_paid_until);
        $ghs = number_format($this->availableBalance, 2, '.', '');

        return "CityShop: {$amount} seller service fee paid. Store active until {$until}.\nAvailable Balance: GHS {$ghs}\nDate: ".NotificationPrivacy::stamp($this->profile->activation_paid_at);
    }
}
