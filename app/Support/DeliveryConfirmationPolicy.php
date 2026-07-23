<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\OrderItem;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Buyer delivery confirmation window (Alibaba-style auto-confirm).
 */
class DeliveryConfirmationPolicy
{
    public static function days(): int
    {
        return max(1, (int) config('marketplace.auto_confirm_delivery_days', 15));
    }

    public static function startedAt(OrderItem $item): ?Carbon
    {
        if ($item->status !== OrderStatus::AwaitingConfirmation) {
            return null;
        }

        $started = $item->awaiting_confirmation_at ?? $item->updated_at;

        return $started ? Carbon::parse($started) : null;
    }

    public static function deadline(OrderItem $item): ?Carbon
    {
        $started = static::startedAt($item);

        return $started?->copy()->addDays(static::days());
    }

    /**
     * Human remaining time, e.g. "20 days, 2 hours".
     */
    public static function remainingLabel(OrderItem $item, ?CarbonInterface $now = null): ?string
    {
        $deadline = static::deadline($item);
        if ($deadline === null) {
            return null;
        }

        $now = $now ? Carbon::instance($now) : now();
        $seconds = max(0, $deadline->getTimestamp() - $now->getTimestamp());

        if ($seconds < 60) {
            return 'less than 1 hour';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);

        $parts = [];
        if ($days > 0) {
            $parts[] = $days === 1 ? '1 day' : "{$days} days";
        }
        if ($hours > 0 || $days === 0) {
            $parts[] = $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return implode(', ', $parts);
    }

    public static function appendToItem(OrderItem $item): void
    {
        if ($item->status !== OrderStatus::AwaitingConfirmation) {
            $item->setAttribute('auto_confirm_in', null);
            $item->setAttribute('auto_confirm_at', null);

            return;
        }

        $deadline = static::deadline($item);
        $item->setAttribute('auto_confirm_in', static::remainingLabel($item));
        $item->setAttribute('auto_confirm_at', $deadline?->toIso8601String());
    }
}
