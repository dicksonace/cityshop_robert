<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\SellerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellerApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SellerProfile $profile) {}

    public function via(object $notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Seller application approved!')
            ->greeting('Congratulations '.$notifiable->name.'!')
            ->line('Your CityShop seller account has been approved.')
            ->line('You can now list products and start selling.')
            ->action('Go to Dashboard', route('seller.dashboard'));
    }

    public function toSms(object $notifiable): string
    {
        return 'CityShop: Your seller application has been approved! Log in to start selling.';
    }
}
