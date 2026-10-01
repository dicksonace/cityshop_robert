<?php

namespace App\Support;

class PaymentReference
{
    public static function recharge(): string
    {
        return self::token();
    }

    public static function order(): string
    {
        return self::token();
    }

    public static function withdrawal(int $id): string
    {
        return strtoupper(dechex(max(1, $id))).self::token();
    }

    public static function withdrawalLedger(int $id): string
    {
        return 'WITHDRAWAL-'.$id;
    }

    public static function gsm(int $orderId): string
    {
        return 'GSM-'.$orderId;
    }

    private static function token(): string
    {
        return strtoupper(bin2hex(random_bytes(6)));
    }
}
