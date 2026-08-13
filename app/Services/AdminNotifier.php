<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\WalletTopUpRequest;
use App\Notifications\AdminWalletDepositNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

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
        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, $notification);
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
}
