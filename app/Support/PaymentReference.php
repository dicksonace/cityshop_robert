<?php

namespace App\Support;

class PaymentReference
{
    public static function recharge(): string
    {
        return 'CITYSHOP-'.self::token();
    }

    public static function order(): string
    {
        return 'CITYSHOP-ORD-'.self::token();
    }

    public static function withdrawal(int $id): string
    {
        return 'WITHDRAWAL-'.$id.'-'.strtoupper(substr(uniqid(), -8));
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
