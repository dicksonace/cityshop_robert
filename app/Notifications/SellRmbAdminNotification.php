<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Enums\SellRmbStatus;
use App\Models\SellRmbTransfer;
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
        $channels = ['mail'];
        if (filled($notifiable->mobile ?? null)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $who = $this->transfer->user?->name ?: 'A buyer';
        $payout = $this->transfer->payout_currency === 'ghs'
            ? 'GH₵'.number_format((float) $this->transfer->ghs_payout, 2)
            : '$'.number_format((float) $this->transfer->usd_payout, 2);

        return (new MailMessage)
            ->subject("Sell RMB {$this->transfer->reference}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'Admin').'!')
            ->line("{$who} submitted a Sell RMB request.")
            ->line('Ref: '.$this->transfer->reference)
            ->line('RMB: ¥'.number_format((float) $this->transfer->rmb_amount, 2))
            ->line("Payout: {$payout}")
            ->line('Status: '.$this->status->label())
            ->action('Open request', route('admin.sell-rmb.show', $this->transfer));
    }

    public function toSms(object $notifiable): string
    {
        $who = $this->transfer->user?->name ?: 'A buyer';

        return "CityShop admin: {$who} submitted Sell RMB {$this->transfer->reference} (¥"
            .number_format((float) $this->transfer->rmb_amount, 2).'). Review in admin.';
    }
}
