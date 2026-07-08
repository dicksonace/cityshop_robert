<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    public static function ensure(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            [
                'available_balance' => 0,
                'pending_balance' => 0,
                'total_earnings' => 0,
                'withdrawn_amount' => 0,
            ]
        );
    }

    public static function adminCredit(User $target, float $amount, User $admin, ?string $note = null): WalletTransaction
    {
        return DB::transaction(function () use ($target, $amount, $admin, $note) {
            $wallet = Wallet::where('user_id', $target->id)->lockForUpdate()->first()
                ?? static::ensure($target);

            $wallet->increment('available_balance', $amount);

            return WalletTransactionService::recordAdminCredit($target->id, $amount, $admin->id, $note);
        });
    }

    public static function creditFromVerifiedTopUp(int $userId, float $amount, string $reference, string $method): bool
    {
        return (bool) DB::transaction(function () use ($userId, $amount, $reference, $method) {
            if (WalletTransaction::where('reference', $reference)->exists()) {
                return false;
            }

            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id' => $userId,
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'total_earnings' => 0,
                    'withdrawn_amount' => 0,
                ]);
            }

            $wallet->increment('available_balance', $amount);
            WalletTransactionService::recordFundAdded($userId, $amount, $method, $reference);

            return true;
        });
    }
}
