<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case CallConfirmed = 'call_confirmed';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
}
