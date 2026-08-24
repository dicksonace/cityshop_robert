<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DisputeStatus;
use App\Enums\KycStatus;
use App\Enums\OrderStatus;
use App\Enums\SellerStatus;
use App\Enums\WalletTopUpStatus;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\KycVerification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\WalletTopUpRequest;
use App\Models\Withdrawal;
use App\Services\ChinaTransferService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function show(OrderService $orders, ChinaTransferService $china): JsonResponse
    {
        return response()->json([
            'stats' => [
                'total_users' => User::count(),
                'total_sellers' => User::where('role', 'seller')->count(),
                'pending_sellers' => SellerProfile::where('status', SellerStatus::Pending)->count(),
                'total_products' => Product::count(),
                'total_orders' => Order::count(),
                'paid_revenue' => (float) Order::where('payment_status', 'paid')->sum('total'),
                'pending_withdrawals' => Withdrawal::where('status', WithdrawalStatus::Pending)->count(),
                'pending_topups' => WalletTopUpRequest::where('status', WalletTopUpStatus::Pending)->count(),
                'pending_rmb' => $china->pendingAdminCount(),
                'pending_kyc' => KycVerification::where('status', KycStatus::Pending)->count(),
                'open_disputes' => Dispute::where('status', DisputeStatus::Open)->count(),
                'pending_funds' => $orders->pendingFundReleaseItemsQuery()->count(),
                'awaiting_confirmation' => OrderItem::where('status', OrderStatus::AwaitingConfirmation)->count(),
                'unprocessed_orders' => $orders->staleUnprocessedItemsQuery(0)->count(),
            ],
            'queues' => [
                'sellers' => SellerProfile::with('user:id,name,email,mobile')
                    ->where('status', SellerStatus::Pending)
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn (SellerProfile $seller) => [
                        'id' => $seller->id,
                        'store_name' => $seller->displayName(),
                        'status' => $seller->status?->value,
                        'created_at' => $seller->created_at?->toIso8601String(),
                        'user' => $seller->user ? [
                            'id' => $seller->user->id,
                            'name' => $seller->user->name,
                            'mobile' => $seller->user->mobile,
                        ] : null,
                    ]),
                'withdrawals' => Withdrawal::with('user:id,name,role,mobile')
                    ->where('status', WithdrawalStatus::Pending)
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(fn (Withdrawal $w) => [
                        'id' => $w->id,
                        'amount' => (float) $w->amount,
                        'network' => $w->network,
                        'momo_number' => $w->momo_number,
                        'created_at' => $w->created_at?->toIso8601String(),
                        'user' => $w->user ? [
                            'name' => $w->user->name,
                            'role' => $w->user->role?->value,
                        ] : null,
                    ]),
            ],
        ]);
    }
}
