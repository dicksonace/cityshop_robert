<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAccountProfileRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\BuyerAccountService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
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
            'buyer' => [
                'id' => $buyer->id,
                'name' => $buyer->name,
                'email' => $buyer->email,
                'mobile' => $buyer->mobile,
                'region' => $buyer->region,
                'city' => $buyer->city,
                'residential_address' => $buyer->residential_address,
                'created_at' => $buyer->created_at?->toIso8601String(),
                'orders_count' => (int) $buyer->orders_count,
                'is_blocked' => $buyer->isBlocked(),
                'block_reason' => $buyer->block_reason,
                'blocked_at' => $buyer->blocked_at?->toIso8601String(),
            ],
            'wallet' => $wallet,
            'transactions' => $transactions,
            'orders' => $orders,
            'conversations' => $conversations,
        ]);
    }

    public function update(UpdateAccountProfileRequest $request, User $buyer): RedirectResponse
    {
        abort_unless($buyer->isBuyer(), 404);

        $validated = $request->validated();
        $buyer->updateAccountDetails($validated['name'], $validated['email'], $validated['mobile']);

        return back()->with('success', 'Buyer account details updated.');
    }

    public function block(Request $request, User $buyer, BuyerAccountService $accounts): RedirectResponse
    {
        abort_unless($buyer->isBuyer(), 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $accounts->block($buyer, $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Buyer blocked. They cannot sign in until you unblock them.');
    }

    public function unblock(User $buyer, BuyerAccountService $accounts): RedirectResponse
    {
        abort_unless($buyer->isBuyer(), 404);

        try {
            $accounts->unblock($buyer);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Buyer unblocked.');
    }

    public function destroy(Request $request, User $buyer, BuyerAccountService $accounts): RedirectResponse
    {
        abort_unless($buyer->isBuyer(), 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
            'confirm_email' => ['required', 'string'],
        ]);

        if (strcasecmp(trim($validated['confirm_email']), trim((string) $buyer->email)) !== 0) {
            return back()->with('error', 'Email confirmation did not match. Account was not deleted.');
        }

        try {
            $accounts->delete($buyer, $validated['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.buyers.index')
            ->with('success', 'Buyer account deleted. Their email and phone can be used to register again.');
    }
}
