<?php

namespace App\Http\Controllers\Seller;

use App\Enums\SellerStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Services\SellerDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private SellerDashboardService $dashboard) {}

    public function index(Request $request): Response
    {
        $seller = $request->user();

        $recentOrders = OrderItem::with(['order.buyer', 'product.images'])
            ->where('seller_id', $seller->id)
            ->visibleToSeller()
            ->latest()
            ->limit(6)
            ->get();

        return Inertia::render('seller/dashboard', [
            'stats' => $this->dashboard->stats($seller),
            'revenueChart' => $this->dashboard->revenueChart($seller),
            'storeHealth' => $this->dashboard->storeHealthScore($seller),
            'recentOrders' => $recentOrders,
            'recentReviews' => $this->dashboard->recentReviews($seller),
            'recentWithdrawals' => $this->dashboard->recentWithdrawals($seller),
            'profile' => $seller->sellerProfile,
            'storeUrl' => $seller->sellerProfile
                ? route('store.show', $seller->sellerProfile->slug, absolute: true)
                : null,
            'orderPipelineCounts' => $this->dashboard->orderPipelineCounts($seller),
        ]);
    }

    public function pending(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->sellerProfile;

        if ($profile?->status === SellerStatus::Suspended) {
            return Inertia::render('seller/blocked', [
                'reason' => $profile->rejection_reason,
            ]);
        }

        return Inertia::render('seller/pending', [
            'applicant' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'submittedAt' => $profile?->updated_at?->toIso8601String(),
            'justSubmitted' => $request->boolean('submitted'),
        ]);
    }
}
