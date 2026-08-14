<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Withdrawal;
use App\Support\NotificationPrivacy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminWithdrawalRequestedNotification extends Notification
{
    public function __construct(public Withdrawal $withdrawal) {}

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
        $user = $this->withdrawal->user;
        $name = trim((string) ($user?->name ?? 'A user')) ?: 'A user';
        $role = $user?->role?->value ?? 'user';
        $amount = NotificationPrivacy::money((float) $this->withdrawal->amount);
        $destination = NotificationPrivacy::maskAccount($this->withdrawal->momo_number);
        $channel = ($this->withdrawal->payout_channel ?? '') === 'bank' ? 'bank' : 'MoMo';
        $when = NotificationPrivacy::stamp($this->withdrawal->created_at);

        return (new MailMessage)
            ->subject("Withdrawal request: {$amount} from {$name}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'Admin').'!')
            ->line("A {$role} requested a CityShop withdrawal.")
            ->line("User: {$name}")
            ->line("Amount: {$amount}")
            ->line("Payout: {$channel} · {$destination}")
            ->line("Date: {$when}")
            ->action('Open withdrawals', route('admin.withdrawals.index'));
    }

    public function toSms(object $notifiable): string
    {
        $user = $this->withdrawal->user;
        $name = trim((string) ($user?->name ?? 'A user')) ?: 'A user';
        $amount = NotificationPrivacy::money((float) $this->withdrawal->amount);
        $channel = ($this->withdrawal->payout_channel ?? '') === 'bank' ? 'bank' : 'MoMo';
        $when = NotificationPrivacy::stamp($this->withdrawal->created_at);

        return "CityShop admin: {$name} requested a {$amount} {$channel} withdrawal. Review in admin. Date: {$when}";
    }
}
