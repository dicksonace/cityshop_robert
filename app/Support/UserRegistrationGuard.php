<?php

namespace App\Support;

use App\Models\User;

class UserRegistrationGuard
{
    public static function emailTakenMessage(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));
        if ($normalized === '') {
            return null;
        }

        $user = User::query()->where('email', $normalized)->first();
        if (! $user) {
            return null;
        }

        if ($user->isBlocked()) {
            return 'This email is linked to a restricted account for security reasons. You cannot register again with it unless an admin removes the account.';
        }

        return 'This email is already registered.';
    }

    public static function mobileTakenMessage(string $mobile): ?string
    {
        $user = GhanaMobile::findUserByMobile($mobile);
        if (! $user) {
            return null;
        }

        if ($user->isBlocked()) {
            return 'This mobile number is linked to a restricted account for security reasons. You cannot register again with it unless an admin removes the account.';
        }

        return 'This mobile number is already registered.';
    }

    /**
     * Free email/mobile on soft-deleted rows so the same details can register again.
     * Handles legacy deletes from before identifiers were released automatically.
     */
    public static function releaseTrashedIdentifiers(?string $email, ?string $mobile): void
    {
        $normalized = strtolower(trim((string) $email));
        if ($normalized !== '') {
            User::onlyTrashed()
                ->where('email', $normalized)
                ->get()
                ->each(function (User $user): void {
                    $user->releaseLoginIdentifiers();
                    $user->save();
                });
        }

        $variants = GhanaMobile::variants((string) $mobile);
        if ($variants !== []) {
            User::onlyTrashed()
                ->whereIn('mobile', $variants)
                ->get()
                ->each(function (User $user): void {
                    $user->releaseLoginIdentifiers();
                    $user->save();
                });
        }
    }
}
