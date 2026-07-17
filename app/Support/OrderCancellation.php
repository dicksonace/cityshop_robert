<?php

namespace App\Support;

class OrderCancellation
{
    public const BY_SELLER = 'seller';

    public const BY_ADMIN = 'admin';

    public const REFUND_COMPLETED = 'completed';

    public const REFUND_NOT_APPLICABLE = 'not_applicable';

    public const REFUND_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    public static function reasons(): array
    {
        return [
            'out_of_stock' => 'Item is out of stock',
            'inventory_mismatch' => 'Inventory mismatch',
            'product_damaged' => 'Product damaged',
            'unable_to_fulfill' => 'Unable to fulfill order',
            'store_unavailable' => 'Store temporarily unavailable',
            'other' => 'Other',
        ];
    }

    public static function label(?string $code): string
    {
        if (! $code) {
            return 'Cancelled';
        }

        return self::reasons()[$code] ?? str_replace('_', ' ', $code);
    }

    /**
     * Statuses a seller may still cancel (before shipment).
     *
     * @return list<string>
     */
    public static function sellerCancellableStatuses(): array
    {
        return ['pending', 'processing', 'packed'];
    }
}
