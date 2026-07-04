<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $buyers = User::query()
            ->where('role', UserRole::Buyer)
            ->with('wallet')
            ->withCount('orders')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/buyers/index', [
            'buyers' => $buyers,
            'search' => $search !== '' ? $search : null,
        ]);
    }

    public function show(User $buyer): Response
    {
        abort_unless($buyer->isBuyer(), 404);

        $buyer->loadCount('orders');
        $wallet = WalletService::ensure($buyer);

        $transactions = WalletTransaction::where('user_id', $buyer->id)
            ->latest()
            ->paginate(15, ['*'], 'transactions_page')
            ->withQueryString();

        $orders = $buyer->orders()
            ->with(['items.product'])
            ->latest()
            ->paginate(10, ['*'], 'orders_page')
            ->withQueryString();

        $conversations = Conversation::query()
            ->where('buyer_id', $buyer->id)
            ->with(['seller:id,name,email', 'product:id,name,slug', 'latestMessage'])
            ->latest('last_message_at')
            ->limit(20)
            ->get();

        return Inertia::render('admin/buyers/show', [
            'buyer' => $buyer,
            'wallet' => $wallet,
            'transactions' => $transactions,
            'orders' => $orders,
            'conversations' => $conversations,
        ]);
    }
}
