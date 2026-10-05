<?php

namespace App\Notifications;

use App\Enums\SellRmbStatus;
use App\Services\AdminNotifier;
use App\Models\SellRmbTransfer;
use App\Support\NotificationPrivacy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellRmbAdminNotification extends Notification
{
    public function __construct(
        public SellRmbTransfer $transfer,
        public SellRmbStatus $status,
    ) {}

    public function via(object $notifiable): array
    {
        return AdminNotifier::channels($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $who = $this->transfer->user?->name ?: 'A buyer';
        $payout = $this->transfer->payout_currency === 'ghs'
            ? NotificationPrivacy::money((float) $this->transfer->ghs_payout)
            : '$'.number_format((float) $this->transfer->usd_payout, 2);

        return (new MailMessage)
            ->subject("Sell RMB {$this->transfer->reference}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'Admin').'!')
            ->line("{$who} submitted a Sell RMB request.")
            ->line('Ref: '.$this->transfer->reference)
            ->line('RMB: '.NotificationPrivacy::rmb((float) $this->transfer->rmb_amount))
            ->line("Payout: {$payout}")
            ->line('Status: '.$this->status->label())
            ->action('Open request', route('admin.sell-rmb.show', $this->transfer));
    }

    public function toSms(object $notifiable): string
    {
        $who = $this->transfer->user?->name ?: 'A buyer';

        $rmb = NotificationPrivacy::rmb((float) $this->transfer->rmb_amount);
        $payout = $this->transfer->payout_currency === 'ghs'
            ? NotificationPrivacy::money((float) $this->transfer->ghs_payout)
            : '$'.number_format((float) $this->transfer->usd_payout, 2);

        return "CityShop admin: {$who} submitted Sell RMB {$this->transfer->reference} ({$rmb}, {$payout}). Review in admin.";
    }
}
