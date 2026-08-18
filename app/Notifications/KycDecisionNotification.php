<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Enums\KycStatus;
use App\Models\KycVerification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycDecisionNotification extends Notification
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
        $name = filled($notifiable->name ?? null) ? $notifiable->name : 'there';
        $notes = trim((string) ($this->kyc->admin_notes ?? ''));

        return match ($this->kyc->status) {
            KycStatus::Approved => (new MailMessage)
                ->subject('Ghana Card verified — you can add wallet funds')
                ->greeting('Hello '.$name.'!')
                ->line('Your Ghana Card has been approved.')
                ->line('You can now transact with the CityShop wallet. Buying with Paystack at checkout was already allowed.'),
            KycStatus::NeedsImprovement => (new MailMessage)
                ->subject('Improve your Ghana Card photos')
                ->greeting('Hello '.$name.'!')
                ->line('The system needs a clearer Ghana Card before you can transact with the CityShop wallet.')
                ->line($notes !== '' ? $notes : 'Please retake the front and back photos so the name and card number are easy to read.')
                ->line('You can still buy items with Paystack while you update this.'),
            default => (new MailMessage)
                ->subject('Ghana Card was not approved')
                ->greeting('Hello '.$name.'!')
                ->line('Your Ghana Card verification was not approved.')
                ->line($notes !== '' ? $notes : 'Please submit a new Ghana Card to transact with the CityShop wallet.')
                ->line('You can still buy items with Paystack.'),
        };
    }

    public function toSms(object $notifiable): string
    {
        return match ($this->kyc->status) {
            KycStatus::Approved => 'CityShop: Your Ghana Card is verified. You can now transact with the CityShop wallet.',
            KycStatus::NeedsImprovement => 'CityShop: Please improve your Ghana Card photos, then submit again to transact with the CityShop wallet.',
            default => 'CityShop: Your Ghana Card was not approved. Update it to transact with the CityShop wallet. You can still pay with Paystack.',
        };
    }
}
