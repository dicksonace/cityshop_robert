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
}
