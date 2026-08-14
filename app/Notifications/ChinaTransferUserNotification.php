<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Enums\ChinaTransferStatus;
use App\Models\ChinaTransfer;
use App\Support\NotificationPrivacy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ChinaTransferUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        $mail = (new MailMessage)
            ->subject($this->subject())
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line($this->line());

        $mail->line('Ref: '.$this->transfer->reference)
            ->line('GHS paid: '.NotificationPrivacy::money((float) $this->transfer->total_payable_ghs))
            ->line('RMB: ¥'.number_format((float) $this->transfer->rmb_amount, 2))
            ->line('Rate: 1 RMB = GH₵'.number_format((float) $this->transfer->ghs_per_rmb, 4))
            ->action('Track transfer', url('/wallet/china-transfer/'.$this->transfer->id));

        return $mail;
    }

    public function toSms(object $notifiable): string
    {
        return 'CityShop: '.$this->line().' Ref '.$this->transfer->reference.'.';
    }

    private function subject(): string
    {
        return match ($this->status) {
            ChinaTransferStatus::Completed => 'Your RMB has been sent',
            ChinaTransferStatus::RmbSent => 'RMB transfer proof is ready',
            ChinaTransferStatus::PaymentRejected => 'China Transfer payment rejected',
            default => 'China Transfer '.$this->status->label(),
        };
    }

    private function line(): string
    {
        return match ($this->status) {
            ChinaTransferStatus::PendingPayment => 'Your Transfer to China was created. Send the GHS payment and keep your proof.',
            ChinaTransferStatus::PaymentSubmitted => 'We received your GHS payment proof and are verifying it.',
            ChinaTransferStatus::PaymentVerification => 'Your GHS payment was verified. We are preparing the Alipay transfer.',
            ChinaTransferStatus::Processing => 'Your RMB transfer is processing.',
            ChinaTransferStatus::RmbSent => 'Your RMB has been sent successfully. Open the app to view proof.',
            ChinaTransferStatus::Completed => 'Your Transfer to China is complete.',
            ChinaTransferStatus::PaymentRejected => $this->transfer->rejection_reason ?: 'Your GHS payment could not be verified.',
            ChinaTransferStatus::Cancelled => 'Your Transfer to China was cancelled.',
            ChinaTransferStatus::TransferFailed => $this->transfer->rejection_reason ?: 'The RMB transfer failed.',
            default => 'Your Transfer to China was updated: '.$this->status->label().'.',
        };
    }
}
