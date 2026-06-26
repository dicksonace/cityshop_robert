<?php

namespace App\Enums;

enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
    case FreeShipping = 'free_shipping';
}
