<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Withdrawal;
use App\Support\NotificationPrivacy;
use App\Support\PayoutNetwork;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalRequestedNotification extends Notification
{
    public function __construct(
        public Withdrawal $withdrawal,
        public ?float $availableBalance = null,
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
        $amount = NotificationPrivacy::money($this->debitedAmount());
        $when = NotificationPrivacy::stamp($this->withdrawal->created_at);
        $ghs = $this->availableBalanceGhs($notifiable);
        $ref = $this->reference();

        $mail = (new MailMessage)
            ->subject("{$amount} debited for withdrawal")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line("{$amount} has been debited from your CityShop wallet{$this->withdrawalReason()}.")
            ->line("Available Balance: GHS {$ghs}");

        if ($ref) {
            $mail->line('Ref: '.$ref);
        }

        return $mail
            ->line("Date: {$when}")
            ->action('View wallet', url('/wallet'));
    }

    public function toSms(object $notifiable): string
    {
        $amount = NotificationPrivacy::money($this->debitedAmount());
        $when = NotificationPrivacy::stamp($this->withdrawal->created_at);
        $ghs = $this->availableBalanceGhs($notifiable);
        $ref = $this->reference();

        $lines = [
            "CityShop: {$amount} debited from your wallet for withdrawal{$this->withdrawalDestination()}.",
            "Available Balance: GHS {$ghs}",
        ];
        if ($ref) {
            $lines[] = "Ref: {$ref}.";
        }
        $lines[] = "Date: {$when}.";

        return implode("\n", $lines);
    }

    private function debitedAmount(): float
    {
        return $this->withdrawal->id
            ? $this->withdrawal->totalDebited()
            : (float) $this->withdrawal->amount;
    }

    private function reference(): ?string
    {
        if (filled($this->withdrawal->paystack_reference)) {
            return (string) $this->withdrawal->paystack_reference;
        }

        if ($this->withdrawal->id) {
            return 'WD-'.$this->withdrawal->id;
        }

        return null;
    }

    private function availableBalanceGhs(object $notifiable): string
    {
        $balance = $this->availableBalance;
        if ($balance === null) {
            $balance = (float) ($notifiable->wallet?->available_balance
                ?? \App\Models\Wallet::query()->where('user_id', $notifiable->id)->value('available_balance')
                ?? 0);
        }

        return number_format($balance, 2, '.', '');
    }

    private function withdrawalReason(): string
    {
        $destination = $this->withdrawalDestination();

        return $destination !== '' ? ' for withdrawal'.$destination : ' for withdrawal';
    }

    private function withdrawalDestination(): string
    {
        $account = trim((string) ($this->withdrawal->momo_number ?? ''));
        if ($account === '') {
            return '';
        }

        $masked = NotificationPrivacy::maskAccount($account);
        $network = PayoutNetwork::label($this->withdrawal->network);

        return $network !== ''
            ? " to {$masked} via {$network}"
            : " to {$masked}";
    }
}
