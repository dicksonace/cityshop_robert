<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentPinResetCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your CityShop payment PIN reset code')
            ->greeting('Hello '.$notifiable->name.'!')
            ->line('Use this code to reset your 4-digit payment PIN:')
            ->line('**'.$this->code.'**')
            ->line('This code expires in 30 minutes. If you did not request a PIN reset, you can ignore this email.')
            ->line('Never share this code with anyone.');
    }
}
