<?php

namespace App\Notifications;

use App\Models\User;
use App\Services\AdminNotifier;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminSellerApplicationNotification extends Notification
{
    public function __construct(
        public User $applicant,
        public string $storeName,
    ) {}

    public function via(object $notifiable): array
    {
        return AdminNotifier::channels($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim((string) ($this->applicant->name ?? '')) ?: 'A seller';

        return (new MailMessage)
            ->subject("Seller application from {$name}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'Admin').'!')
            ->line("{$name} submitted a seller application.")
            ->line('Store: '.$this->storeName)
            ->line('Phone: '.($this->applicant->mobile ?: 'not set'))
            ->action('Review sellers', route('admin.sellers.index', ['status' => 'pending']));
    }

    public function toSms(object $notifiable): string
    {
        $name = trim((string) ($this->applicant->name ?? '')) ?: 'A seller';

        return "CityShop admin: {$name} applied to sell ({$this->storeName}). Review in admin.";
    }
}
