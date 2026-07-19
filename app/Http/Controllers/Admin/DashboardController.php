<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerStatus;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Withdrawal;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $stats = [
            'total_users' => User::count(),
            'total_sellers' => User::where('role', 'seller')->count(),
            'pending_sellers' => SellerProfile::where('status', SellerStatus::Pending)->count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'total_revenue' => Order::where('payment_status', 'paid')->sum('total'),
            'pending_withdrawals' => Withdrawal::where('status', WithdrawalStatus::Pending)->count(),
        ];

        $recentOrders = Order::with('buyer')->latest()->limit(5)->get();
        $pendingSellers = SellerProfile::with('user')->where('status', SellerStatus::Pending)->latest()->limit(5)->get();
        $pendingWithdrawals = Withdrawal::with('user:id,name,email,role')
            ->where('status', WithdrawalStatus::Pending)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Withdrawal $w) => [
                'id' => $w->id,
                'amount' => (float) $w->amount,
                'network' => $w->network,
                'momo_number' => $w->momo_number,
                'account_name' => $w->account_name,
                'created_at' => $w->created_at?->toIso8601String(),
                'user' => $w->user ? [
                    'name' => $w->user->name,
                    'email' => $w->user->email,
                    'role' => $w->user->role?->value,
                ] : null,
            ]);

        return Inertia::render('admin/dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'pendingSellers' => $pendingSellers,
            'pendingWithdrawals' => $pendingWithdrawals,
        ]);
    }
}
