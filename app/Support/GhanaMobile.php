<?php

namespace App\Support;

use App\Models\User;

/**
 * Ghana mobiles are typed as 024…, 24…, 233…, or +233….
 * Login and uniqueness must treat those as one number.
 */
class GhanaMobile
{
    /** Formula-style MSISDN: 233 + 9 digits, or null if not Ghana-shaped. */
    public static function to233(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '00233')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '233') && strlen($digits) === 12) {
            return $digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '233'.substr($digits, 1);
        }

        if (strlen($digits) === 9) {
            return '233'.$digits;
        }

        return null;
    }

    /**
     * Forms we may have stored or that a user might type at login.
     *
     * @return list<string>
     */
    public static function variants(string $phone): array
    {
        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';
        $canonical = static::to233($trimmed);

        $out = [];
        if ($trimmed !== '') {
            $out[] = $trimmed;
        }
        if ($digits !== '') {
            $out[] = $digits;
        }

        if ($canonical) {
            $national = substr($canonical, 3);
            $out[] = $canonical;
            $out[] = '0'.$national;
            $out[] = $national;
            $out[] = '+'.$canonical;
            $out[] = '00'.$canonical;
        }

        return array_values(array_unique(array_filter($out, fn (string $v) => $v !== '')));
    }

    public static function findUserByMobile(string $phone): ?User
    {
        $variants = static::variants($phone);
        if ($variants === []) {
            return null;
        }

        return User::query()->whereIn('mobile', $variants)->first();
    }

    /** True when another account already owns this Ghana number (any format). */
    public static function isTaken(string $phone, ?int $ignoreUserId = null): bool
    {
        $variants = static::variants($phone);
        if ($variants === []) {
            return false;
        }

        $query = User::query()->whereIn('mobile', $variants);
        if ($ignoreUserId) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->exists();
    }
}
