<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletFundingController extends Controller
{
    public function users(Request $request): JsonResponse
    {
        $search = $request->string('search')->trim()->toString();
        $users = User::query()
            ->whereIn('role', [UserRole::Seller, UserRole::Buyer])
            ->with('wallet')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->mobile,
                'email' => $user->email,
                'role' => $user->role?->value,
                'available_balance' => (float) ($user->wallet?->available_balance ?? 0),
            ]);

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
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

        try {
            if ($validated['action'] === 'debit') {
                WalletService::adminDebit($target, $amount, $request->user(), $validated['note'] ?? null);
                $message = 'GH₵'.number_format($amount, 2).' removed from '.$target->name."'s wallet.";
            } else {
                WalletService::adminCredit($target, $amount, $request->user(), $validated['note'] ?? null);
                $message = 'GH₵'.number_format($amount, 2).' added to '.$target->name."'s wallet.";
            }
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $wallet = WalletService::ensure($target->fresh());

        return response()->json([
            'message' => $message,
            'available_balance' => (float) $wallet->available_balance,
        ]);
    }
}
