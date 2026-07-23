<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStatDaily;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProductAnalyticsService
{
    public function recordView(Product $product): void
    {
        $this->safe(function () use ($product) {
            $product->increment('views');
            $this->incrementDaily($product->id, 'views');
        });
    }

    public function recordCartAdd(Product $product, int $quantity = 1): void
    {
        $this->safe(function () use ($product, $quantity) {
            $product->increment('cart_adds', $quantity);
            $this->incrementDaily($product->id, 'cart_adds', $quantity);
        });
    }

    public function recordWishlistAdd(Product $product): void
    {
        $this->safe(fn () => $product->increment('wishlist_adds'));
    }

    public function recordWishlistRemove(Product $product): void
    {
        $this->safe(function () use ($product) {
            if ((int) $product->wishlist_adds > 0) {
                $product->decrement('wishlist_adds');
            }
        });
    }

    public function recordPurchase(Product $product, int $quantity = 1): void
    {
        $this->safe(function () use ($product, $quantity) {
            $product->increment('purchase_count', $quantity);
            $this->incrementDaily($product->id, 'purchases', $quantity);
        });
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
        if (! Schema::hasTable('product_stat_daily')) {
            return;
        }

        if (! in_array($column, ['views', 'cart_adds', 'purchases'], true)) {
            return;
        }

        $date = Carbon::today()->toDateString();
        $now = now();

        // Avoid firstOrCreate + date casts, which can miss existing rows and hit the unique index.
        $updated = DB::table('product_stat_daily')
            ->where('product_id', $productId)
            ->whereDate('date', $date)
            ->update([
                $column => DB::raw("{$column} + {$amount}"),
                'updated_at' => $now,
            ]);

        if ($updated > 0) {
            return;
        }

        try {
            DB::table('product_stat_daily')->insert([
                'product_id' => $productId,
                'date' => $date,
                'views' => $column === 'views' ? $amount : 0,
                'cart_adds' => $column === 'cart_adds' ? $amount : 0,
                'purchases' => $column === 'purchases' ? $amount : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable) {
            DB::table('product_stat_daily')
                ->where('product_id', $productId)
                ->whereDate('date', $date)
                ->update([
                    $column => DB::raw("{$column} + {$amount}"),
                    'updated_at' => $now,
                ]);
        }
    }

    private function safe(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
