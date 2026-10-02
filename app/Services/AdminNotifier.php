<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WalletTopUpRequest;
use App\Notifications\AdminWalletDepositNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Testing\Fakes\NotificationFake;

class AdminNotifier
{
    /** @return Collection<int, User> */
    public static function users(): Collection
    {
        return User::query()
            ->where('role', UserRole::Admin)
            ->get();
    }

    public static function notify(object $notification): void
    {
        $admins = self::users();
        if ($admins->isNotEmpty()) {
            self::deliver($admins, $notification);
        }

        try {
            self::smsAlertNumbers($notification, $admins);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function depositProof(User $user, WalletTopUpRequest $topUp): void
    {
        self::notify(new AdminWalletDepositNotification(
            userName: (string) ($user->name ?? 'A user'),
            userRole: (string) ($user->role?->value ?? 'user'),
            amount: (float) $topUp->amount,
            method: (string) ($topUp->network ?: 'manual'),
            reference: filled($topUp->payment_reference)
                ? (string) $topUp->payment_reference
                : 'proof #'.$topUp->id,
            pendingProof: true,
        ));
    }

    /**
     * Send each channel on its own so a mail failure still lets the SMS go out.
     * Tests keep one combined send so channel assertions stay intact.
     *
     * @param  User|Collection<int, User>  $notifiables
     */
    public static function deliver(User|Collection $notifiables, object $notification): void
    {
        $people = $notifiables instanceof User ? collect([$notifiables]) : $notifiables;
        if ($people->isEmpty()) {
            return;
        }

        if (Notification::getFacadeRoot() instanceof NotificationFake) {
            Notification::send($people, $notification);

            return;
        }

        foreach ($people as $person) {
            $channels = method_exists($notification, 'via') ? (array) $notification->via($person) : ['mail'];
            foreach ($channels as $channel) {
                try {
                    Notification::sendNow($person, $notification, [$channel]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
    }

    /**
     * SMS extra admin alert numbers once, skipping numbers already on admin accounts.
     *
     * @param  Collection<int, User>  $admins
     */
    private static function smsAlertNumbers(object $notification, Collection $admins): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $extras = PlatformSettings::adminAlertNumbers();
        if ($extras === []) {
            return;
        }

        $sms = app(SmsService::class);
        $already = [];
        foreach ($admins as $admin) {
            foreach ([$admin->mobile ?? null, $admin->whatsapp ?? null] as $phone) {
                $msisdn = is_string($phone) ? $sms->normalizeGhanaMsisdn($phone) : null;
                if ($msisdn) {
                    $already[$msisdn] = true;
                }
            }
        }

        $message = $notification->toSms($admins->first() ?? new User);
        foreach ($extras as $phone) {
            $msisdn = $sms->normalizeGhanaMsisdn($phone);
            if (! $msisdn || isset($already[$msisdn])) {
                continue;
            }
            $already[$msisdn] = true;
            $sms->send($phone, $message);
        }
    }
}
