<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\SellerProfile;
use App\Support\NotificationPrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerActivationDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SellerProfile $profile) {}

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

        return (new MailMessage)
            ->subject("Pay {$amount} seller service fee")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line("CityShop admin asked you to pay a {$amount} annual seller service fee.")
            ->line('Until you pay, buyers cannot see your products and you cannot post new listings.')
            ->line('You can still withdraw funds and recharge your wallet.')
            ->line('After payment your store stays active for 1 year.')
            ->action('Pay service fee', route('seller.activation.show'));
    }

    public function toSms(object $notifiable): string
    {
        $amount = NotificationPrivacy::money((float) $this->profile->activation_fee_amount);

        return "CityShop: Pay {$amount} annual seller service fee to keep your store live. Products are hidden until you pay. You can still withdraw and recharge.";
    }
}
