<?php

namespace App\Enums;

enum SellerPaymentMethodType: string
{
    case MobileMoney = 'mobile_money';
    case Bank = 'bank';
    case Paypal = 'paypal';
    case Stripe = 'stripe';
    case Other = 'other';
}
