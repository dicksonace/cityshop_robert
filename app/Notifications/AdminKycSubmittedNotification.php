<?php

namespace App\Notifications;

use App\Models\KycVerification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminKycSubmittedNotification extends Notification
{
    public function __construct(public KycVerification $kyc) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $this->kyc->user;
        $name = trim((string) ($user?->name ?? 'A user')) ?: 'A user';

        return (new MailMessage)
            ->subject("Ghana Card KYC from {$name}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'Admin').'!')
            ->line("{$name} submitted a Ghana Card for wallet verification.")
            ->line('Card: '.$this->kyc->ghana_card_number)
            ->line('Approve, reject, or ask them to improve the photos before they can store money in their wallet.')
            ->action('Review KYC', url('/admin/dashboard'));
    }
}
