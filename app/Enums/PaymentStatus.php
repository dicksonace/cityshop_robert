<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
