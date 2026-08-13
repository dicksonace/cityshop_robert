<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Wallet;
use App\Support\NotificationPrivacy;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WalletFundedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public float $amount,
        public string $method,
        public string $reference,
        public ?float $availableBalance = null,
        public ?CarbonInterface $creditedAt = null,
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
        $amount = NotificationPrivacy::money($this->amount);
        $method = NotificationPrivacy::fundingMethod($this->method);
        $when = NotificationPrivacy::stamp($this->creditedAt);
        $ghs = $this->availableBalanceGhs($notifiable);

        return (new MailMessage)
            ->subject("{$amount} credited to your CityShop wallet")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'there').'!')
            ->line("{$amount} has been credited to your CityShop wallet via {$method}.")
            ->line("Available Balance: GHS {$ghs}")
            ->line('Ref: '.$this->reference)
            ->line("Date: {$when}")
            ->action('View wallet', url('/wallet'));
    }

    public function toSms(object $notifiable): string
    {
        $when = NotificationPrivacy::stamp($this->creditedAt);
        $ghs = $this->availableBalanceGhs($notifiable);

        return 'CityShop: '.NotificationPrivacy::money($this->amount)
            ." credited to your wallet.\nAvailable Balance: GHS {$ghs}\nRef: {$this->reference}.\nDate: {$when}.";
    }

    private function availableBalanceGhs(object $notifiable): string
    {
        $balance = $this->availableBalance;
        if ($balance === null) {
            $balance = (float) ($notifiable->wallet?->available_balance
                ?? Wallet::query()->where('user_id', $notifiable->id)->value('available_balance')
                ?? 0);
        }

        return number_format($balance, 2, '.', '');
    }
}
