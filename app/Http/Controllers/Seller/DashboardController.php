<?php

namespace App\Http\Controllers\Seller;

use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $seller = $request->user();
        $wallet = $seller->wallet;

        $stats = [
            'total_products' => Product::where('seller_id', $seller->id)->count(),
            'pending_products' => Product::where('seller_id', $seller->id)->where('status', ProductStatus::Pending)->count(),
            'total_orders' => OrderItem::where('seller_id', $seller->id)->count(),
            'pending_orders' => OrderItem::where('seller_id', $seller->id)->where('status', OrderStatus::Pending)->count(),
            'available_balance' => $wallet?->available_balance ?? 0,
            'pending_balance' => $wallet?->pending_balance ?? 0,
            'total_earnings' => $wallet?->total_earnings ?? 0,
        ];

        $recentOrders = OrderItem::with(['order.buyer', 'product'])
            ->where('seller_id', $seller->id)
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('seller/dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'profile' => $seller->sellerProfile,
            'storeUrl' => $seller->sellerProfile
                ? route('store.show', $seller->sellerProfile->slug, absolute: true)
                : null,
        ]);
    }

    public function pending(Request $request): Response
    {
        $profile = $request->user()->sellerProfile;

        if ($profile?->status === SellerStatus::Suspended) {
            return Inertia::render('seller/blocked', [
                'reason' => $profile->rejection_reason,
            ]);
        }

        return Inertia::render('seller/pending');
    }
}
