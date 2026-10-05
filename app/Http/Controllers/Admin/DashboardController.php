<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SellerStatus;
use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerProfile;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\GsmToolService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, GsmToolService $gsm): Response
    {
        $stats = [
            'total_users' => User::count(),
            'total_sellers' => User::where('role', 'seller')->count(),
            'pending_sellers' => SellerProfile::where('status', SellerStatus::Pending)->count(),
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'total_revenue' => Order::where('payment_status', 'paid')->sum('total'),
            'pending_withdrawals' => Withdrawal::where('status', WithdrawalStatus::Pending)->count(),
            'pending_gsm_tools' => $gsm->pendingAdminCount(),
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

        $alerts = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('type', 'admin_action')
            ->where('created_at', '>=', now()->subDays(2))
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (AppNotification $alert) => [
                'id' => $alert->id,
                'title' => $alert->title,
                'body' => $alert->body,
                'created_at' => $alert->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'pendingSellers' => $pendingSellers,
            'pendingWithdrawals' => $pendingWithdrawals,
            'alerts' => $alerts,
        ]);
    }
}
