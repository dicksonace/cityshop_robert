<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStatDaily;
use Carbon\Carbon;

class ProductAnalyticsService
{
    public function recordView(Product $product): void
    {
        $product->increment('views');
        $this->incrementDaily($product->id, 'views');
    }

    public function recordCartAdd(Product $product, int $quantity = 1): void
    {
        $product->increment('cart_adds', $quantity);
        $this->incrementDaily($product->id, 'cart_adds', $quantity);
    }

    public function recordWishlistAdd(Product $product): void
    {
        $product->increment('wishlist_adds');
    }

    public function recordPurchase(Product $product, int $quantity = 1): void
    {
        $product->increment('purchase_count', $quantity);
        $this->incrementDaily($product->id, 'purchases', $quantity);
    }

    /**
     * @return array{views: int, cart_adds: int, wishlist_adds: int, purchases: int, revenue: float, conversion_rate: float, chart: array<int, array{date: string, views: int, cart_adds: int, purchases: int}>}
     */
    public function statsForProduct(Product $product, int $days = 30): array
    {
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $daily = ProductStatDaily::where('product_id', $product->id)
            ->where('date', '>=', $start->toDateString())
            ->orderBy('date')
            ->get()
            ->keyBy(fn ($row) => $row->date->format('Y-m-d'));

        $chart = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->format('Y-m-d');
            $row = $daily->get($date);
            $chart[] = [
                'date' => $date,
                'views' => $row?->views ?? 0,
                'cart_adds' => $row?->cart_adds ?? 0,
                'purchases' => $row?->purchases ?? 0,
            ];
        }

        $revenue = (float) $product->orderItems()
            ->whereNotIn('status', ['cancelled', 'refunded'])
            ->sum('seller_amount');

        $views = (int) $product->views;
        $purchases = (int) $product->purchase_count;

        return [
            'views' => $views,
            'cart_adds' => (int) $product->cart_adds,
            'wishlist_adds' => (int) $product->wishlist_adds,
            'purchases' => $purchases,
            'revenue' => $revenue,
            'conversion_rate' => $views > 0 ? round(($purchases / $views) * 100, 2) : 0,
            'chart' => $chart,
        ];
    }

    private function incrementDaily(int $productId, string $column, int $amount = 1): void
    {
        $date = Carbon::today()->toDateString();

        $stat = ProductStatDaily::firstOrCreate(
            ['product_id' => $productId, 'date' => $date],
            ['views' => 0, 'cart_adds' => 0, 'purchases' => 0],
        );

        $stat->increment($column, $amount);
    }
}
