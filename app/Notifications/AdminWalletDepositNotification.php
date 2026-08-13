<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Support\NotificationPrivacy;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminWalletDepositNotification extends Notification
{
    public function __construct(
        public string $userName,
        public string $userRole,
        public float $amount,
        public string $method,
        public string $reference,
        public bool $pendingProof = false,
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
        $when = NotificationPrivacy::stamp();
        $who = trim($this->userName) !== '' ? $this->userName : 'A user';
        $role = $this->userRole !== '' ? $this->userRole : 'user';

        $mail = (new MailMessage)
            ->subject($this->pendingProof
                ? "Deposit proof: {$amount} from {$who}"
                : "Deposit: {$amount} from {$who}")
            ->greeting('Hello '.(filled($notifiable->name ?? null) ? $notifiable->name : 'Admin').'!');

        if ($this->pendingProof) {
            $mail->line("A {$role} submitted a manual deposit proof for review.")
                ->line("User: {$who}")
                ->line("Amount: {$amount}")
                ->line("Network / method: {$method}")
                ->line('Reference: '.$this->reference)
                ->line("Date: {$when}")
                ->action('Review deposits', route('admin.manual-top-ups.index'));
        } else {
            $mail->line("A {$role} wallet was credited.")
                ->line("User: {$who}")
                ->line("Amount: {$amount}")
                ->line("Method: {$method}")
                ->line('Reference: '.$this->reference)
                ->line("Date: {$when}")
                ->action('Open transactions', route('admin.transactions.index'));
        }

        return $mail;
    }

    public function toSms(object $notifiable): string
    {
        $amount = NotificationPrivacy::money($this->amount);
        $who = trim($this->userName) !== '' ? $this->userName : 'A user';
        $when = NotificationPrivacy::stamp();

        if ($this->pendingProof) {
            return "CityShop admin: {$who} submitted a {$amount} deposit proof. Review in admin. Date: {$when}";
        }

        return "CityShop admin: {$who} deposited {$amount} via "
            .NotificationPrivacy::fundingMethod($this->method)
            .". Ref {$this->reference}. Date: {$when}";
    }
}
