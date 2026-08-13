<?php

namespace App\Support;

class ResetChannel
{
    public const EMAIL = 'email';

    public const SMS = 'sms';

    public static function parse(mixed $value): string
    {
        return strtolower(trim((string) $value)) === self::SMS
            ? self::SMS
            : self::EMAIL;
    }

    public static function hint(string $via, ?string $email, ?string $mobile): ?string
    {
        if ($via === self::SMS) {
            return filled($mobile) ? NotificationPrivacy::maskAccount($mobile) : null;
        }

        return filled($email) ? NotificationPrivacy::maskEmail($email) : null;
    }
}
