<?php

namespace App\Enums;

enum PaymentChannel: string
{
    case Marketplace = 'marketplace';
    case Direct = 'direct';
}
