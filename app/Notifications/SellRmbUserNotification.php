<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Enums\SellRmbStatus;
use App\Models\SellRmbTransfer;
use App\Support\SellRmbSms;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SellRmbUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public SellRmbTransfer $transfer,
        public SellRmbStatus $status,
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail'];
        if (filled($notifiable->mobile ?? null) && SellRmbSms::sendsToUser($this->status)) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line($this->line());

        $payoutLabel = $this->transfer->payout_currency === 'ghs'
            ? 'GH₵'.number_format((float) $this->transfer->ghs_payout, 2)
            : '$'.number_format((float) $this->transfer->usd_payout, 2);

        $mail->line('Ref: '.$this->transfer->reference)
            ->line('RMB sold: ¥'.number_format((float) $this->transfer->rmb_amount, 2))
            ->line('Estimated payout: '.$payoutLabel)
            ->line('Rate: 1 RMB = $'.number_format((float) $this->transfer->usd_per_rmb, 6))
            ->action('Track Sell RMB', url('/wallet/sell-rmb/'.$this->transfer->id));

        return $mail;
    }

    public function toSms(object $notifiable): string
    {
        return SellRmbSms::userMessage(
            $this->transfer,
            $this->status,
            filled($notifiable->name ?? null) ? (string) $notifiable->name : null,
        );
    }

    private function subject(): string
    {
        return match ($this->status) {
            SellRmbStatus::Completed => 'Your Sell RMB payout is complete',
            SellRmbStatus::Paid => 'Your Sell RMB payout was sent',
            SellRmbStatus::Rejected => 'Sell RMB rejected',
            default => 'Sell RMB '.$this->status->label(),
        };
    }

    private function line(): string
    {
        return match ($this->status) {
            SellRmbStatus::Submitted => 'Your Sell RMB request was submitted. We are reviewing your RMB payment.',
            SellRmbStatus::RmbVerification => 'We are verifying the RMB you sent.',
            SellRmbStatus::RmbReceived => 'Your RMB was received. We are preparing your payout.',
            SellRmbStatus::PayoutProcessing => 'Your payout is processing.',
            SellRmbStatus::Paid => 'Your payout has been sent. Open the app to view proof.',
            SellRmbStatus::Completed => 'Your Sell RMB request is complete.',
            SellRmbStatus::Rejected => $this->transfer->rejection_reason ?: 'Your RMB payment could not be verified.',
            SellRmbStatus::Cancelled => 'Your Sell RMB request was cancelled.',
            SellRmbStatus::Failed => $this->transfer->rejection_reason ?: 'Your Sell RMB payout failed.',
        };
    }
}
