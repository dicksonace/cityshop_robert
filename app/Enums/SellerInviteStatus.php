<?php

namespace App\Enums;

enum SellerInviteStatus: string
{
    case Pending = 'pending';
    case Used = 'used';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
