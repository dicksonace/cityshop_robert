<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Enums\ChinaTransferStatus;
use App\Models\ChinaTransfer;
use App\Support\NotificationPrivacy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChinaTransferAdminNotification extends Notification
{
    public function __construct(
        public ChinaTransfer $transfer,
        public ChinaTransferStatus $status,
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
        $who = $this->transfer->user?->name ?: 'A buyer';
        $amount = NotificationPrivacy::money((float) $this->transfer->total_payable_ghs);

        return (new MailMessage)
            ->subject("China Transfer {$this->transfer->reference}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'Admin').'!')
            ->line("{$who} submitted a Transfer to China.")
            ->line('Ref: '.$this->transfer->reference)
            ->line("Amount: {$amount}")
            ->line('RMB: ¥'.number_format((float) $this->transfer->rmb_amount, 2))
            ->line('Status: '.$this->status->label())
            ->action('Open transfer', route('admin.china-transfers.show', $this->transfer));
    }

    public function toSms(object $notifiable): string
    {
        $who = $this->transfer->user?->name ?: 'A buyer';
        $amount = NotificationPrivacy::money((float) $this->transfer->total_payable_ghs);

        return "CityShop admin: {$who} submitted China Transfer {$this->transfer->reference} ({$amount}). Review in admin.";
    }
}
