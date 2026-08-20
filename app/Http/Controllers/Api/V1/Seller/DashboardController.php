<?php

namespace App\Http\Controllers\Api\V1\Seller;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\SellerDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DashboardController extends Controller
{
    public function __construct(private SellerDashboardService $dashboard) {}

    public function show(Request $request): JsonResponse
    {
        $seller = $request->user();
        $profile = $seller->sellerProfile;

        $recentOrders = OrderItem::with(['order.buyer:id,name', 'product.images'])
            ->where('seller_id', $seller->id)
            ->visibleToSeller()
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (OrderItem $item) {
                $imagePath = $item->product?->primaryImage()?->path;
                $amount = (float) ($item->seller_amount ?: $item->unit_price * $item->quantity);

                return [
                    'id' => $item->id,
                    'order_id' => $item->order_id,
                    'order_number' => $item->order?->order_number,
                    'product_name' => $item->product?->name,
                    'product_image' => $this->publicUrl($imagePath),
                    'buyer_name' => $item->order?->buyer?->name,
                    'status' => $item->status?->value ?? (string) $item->status,
                    'quantity' => (int) $item->quantity,
                    'amount' => round($amount, 2),
                    'created_at' => $item->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $shopPhoto = $profile?->shop_photo;

        return response()->json([
            'stats' => $this->dashboard->stats($seller),
            'order_pipeline_counts' => $this->dashboard->orderPipelineCounts($seller),
            'store_health' => $this->dashboard->storeHealthScore($seller),
            'revenue_chart' => $this->dashboard->revenueChart($seller, 14),
            'recent_orders' => $recentOrders,
            'profile' => $profile ? [
                'store_name' => $profile->displayName(),
                'slug' => $profile->slug,
                'status' => $profile->status?->value,
                'shop_photo' => $this->publicUrl($shopPhoto),
                'rating' => $profile->rating !== null ? (float) $profile->rating : null,
                'total_sales' => (int) ($profile->total_sales ?? 0),
                'needs_activation' => $profile->needsActivationPayment(),
                'activation_fee' => (float) ($profile->activation_fee_amount ?? 0),
            ] : null,
            'store_url' => $profile?->slug
                ? rtrim((string) config('app.url'), '/').'/store/'.$profile->slug
                : null,
        ]);
    }

    private function publicUrl(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        return str_starts_with($path, 'http')
            ? $path
            : Storage::disk('public')->url($path);
    }
}
