<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\KycVerification;
use App\Support\NotificationPrivacy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminKycSubmittedNotification extends Notification
{
    public function __construct(public KycVerification $kyc) {}

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
        $user = $this->kyc->user;
        $name = trim((string) ($user?->name ?? 'A user')) ?: 'A user';
        $when = NotificationPrivacy::stamp($this->kyc->submitted_at ?? $this->kyc->created_at);

        return (new MailMessage)
            ->subject("Ghana Card KYC from {$name}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'Admin').'!')
            ->line("{$name} submitted a Ghana Card for wallet verification.")
            ->line('Card: '.$this->kyc->ghana_card_number)
            ->line("Date: {$when}")
            ->line('Approve, reject, or ask them to improve the photos before they can store money in their wallet.')
            ->action('Review KYC', route('admin.dashboard'));
    }

    public function toSms(object $notifiable): string
    {
        $user = $this->kyc->user;
        $name = trim((string) ($user?->name ?? 'A user')) ?: 'A user';
        $card = (string) ($this->kyc->ghana_card_number ?? '');
        $when = NotificationPrivacy::stamp($this->kyc->submitted_at ?? $this->kyc->created_at);

        return "CityShop admin: {$name} submitted Ghana Card KYC"
            .($card !== '' ? " ({$card})" : '')
            .". Review in Admin → Ghana Card KYC. Date: {$when}";
    }
}
