<?php

namespace App\Support;

/**
 * Human labels for MoMo networks and Ghana banks used on withdrawals.
 */
class PayoutNetwork
{
    public static function isMomo(?string $code): bool
    {
        return in_array($code, ['mtn', 'telecel', 'airteltigo'], true);
    }

    public static function isBank(?string $code): bool
    {
        return GhanaBanks::isBank($code);
    }

    public static function type(?string $code): string
    {
        return self::isBank($code) ? 'bank' : 'momo';
    }

    public static function label(?string $code): string
    {
        return match ($code) {
            'mtn' => 'MTN Mobile Money',
            'telecel' => 'Telecel Cash',
            'airteltigo' => 'AirtelTigo Money',
            default => GhanaBanks::isBank($code) ? GhanaBanks::label($code) : ucfirst((string) $code),
        };
    }
}
