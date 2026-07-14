<?php

namespace App\Enums;

enum WalletTopUpStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
