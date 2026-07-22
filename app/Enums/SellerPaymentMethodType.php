<?php

namespace App\Enums;

enum SellerPaymentMethodType: string
{
    case MobileMoney = 'mobile_money';
    case Bank = 'bank';
    /** @deprecated Kept for existing rows; not offered when adding methods. */
    case Paypal = 'paypal';
    /** @deprecated Kept for existing rows; not offered when adding methods. */
    case Stripe = 'stripe';
    /** @deprecated Kept for existing rows; not offered when adding methods. */
    case Other = 'other';

    /** Types sellers can select when adding a payment method. */
    public static function creatable(): array
    {
        return [
            self::MobileMoney,
            self::Bank,
        ];
    }
}
