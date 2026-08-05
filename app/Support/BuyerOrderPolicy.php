<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Buyer My Orders visibility and refund-request window (default: 2 months).
 */
class BuyerOrderPolicy
{
    public static function months(): int
    {
        return max(1, (int) config('marketplace.buyer_order_months', 2));
    }

    public static function cutoff(): Carbon
    {
        return now()->subMonths(static::months());
    }

    public static function withinWindow(?CarbonInterface $createdAt): bool
    {
        if ($createdAt === null) {
            return false;
        }

        return $createdAt->greaterThanOrEqualTo(static::cutoff());
    }

    public static function canRequestRefund(Order $order): bool
    {
        // Never offer refunds for unpaid / cancelled-before-pay checkouts.
        if (! in_array($order->payment_status, [
            PaymentStatus::Paid,
            PaymentStatus::Partial,
            PaymentStatus::Refunded,
        ], true)) {
            return false;
        }

        return static::withinWindow($order->created_at);
    }
}
