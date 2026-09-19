<?php

namespace App\Support;

class PaymentReference
{
    public static function recharge(): string
    {
        return 'cityshop-'.self::token();
    }

    public static function order(): string
    {
        return 'cityshop-'.self::token();
    }

    public static function withdrawal(int $id): string
    {
        return 'cityshop-'.$id.'-'.strtoupper(substr(uniqid(), -8));
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
        return strtoupper(str_replace('.', '', uniqid('', true)));
    }
}
