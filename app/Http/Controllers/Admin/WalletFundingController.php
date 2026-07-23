<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WalletFundingController extends Controller
{
    public function index(Request $request): Response
    {
        $role = $request->get('role', 'all');
        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->whereIn('role', [UserRole::Seller, UserRole::Buyer])
            ->with(['wallet', 'sellerProfile:id,user_id,business_name,store_name'])
            ->when($role === 'seller', fn ($q) => $q->where('role', UserRole::Seller))
            ->when($role === 'buyer', fn ($q) => $q->where('role', UserRole::Buyer))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'role' => $user->role->value,
                'store_name' => $user->sellerProfile?->business_name ?? $user->sellerProfile?->store_name,
                'available_balance' => (float) ($user->wallet?->available_balance ?? 0),
            ]);

        $recentFundings = WalletTransaction::query()
            ->whereIn('type', [
                \App\Enums\WalletTransactionType::FundAdded,
                \App\Enums\WalletTransactionType::FundRemoved,
            ])
            ->where(function ($q) {
                $q->where('reference', 'like', 'ADMIN-%')
                    ->orWhere('reference', 'like', 'ADMIN-DEBIT-%');
            })
            ->with('user:id,name,email,role')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'amount' => (float) $tx->amount,
                'type' => $tx->type->value,
                'description' => $tx->description,
                'created_at' => $tx->created_at?->toIso8601String(),
                'user' => $tx->user ? [
                    'id' => $tx->user->id,
                    'name' => $tx->user->name,
                    'role' => $tx->user->role->value,
                ] : null,
            ]);

        return Inertia::render('admin/wallet-funding/index', [
            'users' => $users,
            'role' => $role,
            'search' => $search !== '' ? $search : null,
            'recentFundings' => $recentFundings,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->whereIn('role', [UserRole::Seller->value, UserRole::Buyer->value]),
            ],
            'action' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'numeric', 'min:0.5', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $target = User::findOrFail($validated['user_id']);
        $amount = (float) $validated['amount'];
        $action = $validated['action'];

        try {
            if ($action === 'debit') {
                WalletService::adminDebit(
                    $target,
                    $amount,
                    $request->user(),
                    $validated['note'] ?? null,
                );

                return back()->with(
                    'success',
                    'GH₵'.number_format($amount, 2).' removed from '.$target->name."'s wallet.",
                );
            }

            WalletService::adminCredit(
                $target,
                $amount,
                $request->user(),
                $validated['note'] ?? null,
            );

            return back()->with(
                'success',
                'GH₵'.number_format($amount, 2).' added to '.$target->name."'s wallet.",
            );
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
