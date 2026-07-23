<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Models\Withdrawal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SellerDashboardService
{
    public function stats(User $seller): array
    {
        $sellerId = $seller->id;
        $wallet = $seller->wallet;

        $orderQuery = OrderItem::where('seller_id', $sellerId)->visibleToSeller();

        return [
            'total_products' => Product::where('seller_id', $sellerId)->count(),
            'live_products' => Product::where('seller_id', $sellerId)->where('status', ProductStatus::Approved)->count(),
            'pending_products' => Product::where('seller_id', $sellerId)->where('status', ProductStatus::Pending)->count(),
            'out_of_stock' => Product::where('seller_id', $sellerId)->where('quantity', 0)->where('is_preorder', false)->count(),
            'total_orders' => (clone $orderQuery)->count(),
            'pending_orders' => (clone $orderQuery)->where('status', OrderStatus::Pending)->count(),
            'processing_orders' => (clone $orderQuery)->whereIn('status', [OrderStatus::Processing, OrderStatus::Packed])->count(),
            'delivered_orders' => (clone $orderQuery)->where('status', OrderStatus::Delivered)->count(),
            'cancelled_orders' => (clone $orderQuery)->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded])->count(),
            'available_balance' => $wallet?->available_balance ?? 0,
            'pending_balance' => $wallet?->pending_balance ?? 0,
            'withdrawable_balance' => $wallet?->available_balance ?? 0,
            'total_earnings' => $wallet?->total_earnings ?? 0,
            'withdrawn_amount' => $wallet?->withdrawn_amount ?? 0,
            'product_views' => (int) Product::where('seller_id', $sellerId)->sum('views'),
            'average_rating' => round((float) Product::where('seller_id', $sellerId)->where('review_count', '>', 0)->avg('rating'), 1),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function orderPipelineCounts(User $seller): array
    {
        $base = OrderItem::where('seller_id', $seller->id)->visibleToSeller();

        return [
            'pending' => (clone $base)->where('status', OrderStatus::Pending)->count(),
            'processing' => (clone $base)->where('status', OrderStatus::Processing)->count(),
            'packed' => (clone $base)->where('status', OrderStatus::Packed)->count(),
            'shipped' => (clone $base)->where('status', OrderStatus::Shipped)->count(),
            'awaiting_confirmation' => (clone $base)->where('status', OrderStatus::AwaitingConfirmation)->count(),
            'delivered' => (clone $base)->where('status', OrderStatus::Delivered)->count(),
        ];
    }

    /**
     * @return array<int, array{date: string, revenue: float, orders: int}>
     */
    public function revenueChart(User $seller, int $days = 30): array
    {
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $items = OrderItem::where('seller_id', $seller->id)
            ->visibleToSeller()
            ->where('created_at', '>=', $start)
            ->whereNotIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded])
            ->get(['created_at', 'seller_amount', 'quantity', 'unit_price']);

        $byDate = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->format('Y-m-d');
            $byDate[$date] = ['date' => $date, 'revenue' => 0.0, 'orders' => 0];
        }

        foreach ($items as $item) {
            $date = $item->created_at->format('Y-m-d');
            if (! isset($byDate[$date])) {
                continue;
            }
            $byDate[$date]['revenue'] += (float) ($item->seller_amount ?: $item->unit_price * $item->quantity);
            $byDate[$date]['orders']++;
        }

        return array_values($byDate);
    }

    public function storeHealthScore(User $seller): array
    {
        $profile = $seller->sellerProfile;
        $score = 0;
        $tips = [];

        if ($profile?->shop_photo) {
            $score += 10;
        } else {
            $tips[] = 'Add a store logo to build trust.';
        }

        if ($profile?->store_description) {
            $score += 10;
        } else {
            $tips[] = 'Complete your store profile with a description.';
        }

        if ($seller->whatsapp) {
            $score += 10;
        }

        $liveCount = Product::where('seller_id', $seller->id)->where('status', ProductStatus::Approved)->count();
        if ($liveCount >= 5) {
            $score += 20;
        } elseif ($liveCount > 0) {
            $score += 10;
            $tips[] = 'Upload more products — aim for at least 5 live listings.';
        } else {
            $tips[] = 'Add your first product to start selling.';
        }

        $avgRating = (float) Product::where('seller_id', $seller->id)->where('review_count', '>', 0)->avg('rating');
        if ($avgRating >= 4) {
            $score += 20;
        } elseif ($avgRating > 0) {
            $score += 10;
            $tips[] = 'Improve product quality and service to boost ratings.';
        }

        $totalOrders = OrderItem::where('seller_id', $seller->id)->visibleToSeller()->count();
        $cancelled = OrderItem::where('seller_id', $seller->id)
            ->visibleToSeller()
            ->whereIn('status', [OrderStatus::Cancelled, OrderStatus::Refunded])
            ->count();
        if ($totalOrders > 0) {
            $cancelRate = $cancelled / $totalOrders;
            if ($cancelRate < 0.05) {
                $score += 15;
            } elseif ($cancelRate < 0.15) {
                $score += 8;
            } else {
                $tips[] = 'Reduce order cancellations to improve your store score.';
            }
        } else {
            $score += 10;
        }

        $delivered = OrderItem::where('seller_id', $seller->id)->visibleToSeller()->where('status', OrderStatus::Delivered)->count();
        if ($totalOrders > 0 && $delivered / $totalOrders >= 0.5) {
            $score += 15;
        } elseif ($totalOrders === 0) {
            $score += 10;
        } else {
            $tips[] = 'Fulfill orders quickly to improve your fulfillment rate.';
        }

        $score = min(100, $score);
        $stars = round($score / 20, 1);

        return [
            'score' => $score,
            'stars' => $stars,
            'tips' => array_slice($tips, 0, 3),
        ];
    }

    public function recentReviews(User $seller, int $limit = 5): Collection
    {
        return Review::with(['user', 'product:id,name'])
            ->whereHas('product', fn ($q) => $q->where('seller_id', $seller->id))
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function recentWithdrawals(User $seller, int $limit = 5): Collection
    {
        return Withdrawal::where('user_id', $seller->id)
            ->latest()
            ->limit($limit)
            ->get();
    }
}
