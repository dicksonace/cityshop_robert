<?php

namespace App\Support;

use App\Models\Product;

class ProductStock
{
    /**
     * Available units for cart/checkout. Null means preorder (no hard stock cap beyond 99).
     */
    public static function available(Product $product): ?int
    {
        if ($product->is_preorder) {
            return null;
        }

        return max(0, (int) $product->quantity);
    }

    public static function canFulfill(Product $product, int $quantity): bool
    {
        if ($quantity < 1) {
            return false;
        }

        $available = static::available($product);
        if ($available === null) {
            return $quantity <= 99;
        }

        return $quantity <= $available;
    }

    public static function exceededMessage(Product $product): string
    {
        $left = static::available($product) ?? 0;

        if ($left < 1) {
            return 'Out of stock. Contact seller.';
        }

        return "Out of stock based on your quantity. {$left} left in stock. Contact seller.";
    }

    /**
     * Max qty allowed in cart for this product (for UI + validation).
     */
    public static function maxCartQuantity(Product $product): int
    {
        $available = static::available($product);

        if ($available === null) {
            return 99;
        }

        return min(99, $available);
    }
}
