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

    /**
     * Manually remove funds from available balance (admin clawback / adjustment).
     *
     * @throws \RuntimeException when balance is insufficient
     */
    public static function adminDebit(User $target, float $amount, User $admin, ?string $note = null): WalletTransaction
    {
        return DB::transaction(function () use ($target, $amount, $admin, $note) {
            $wallet = Wallet::where('user_id', $target->id)->lockForUpdate()->first()
                ?? static::ensure($target);

            $available = (float) $wallet->available_balance;
            if ($available + 0.0001 < $amount) {
                throw new \RuntimeException(
                    'Insufficient available balance. '.$target->name.' has GH₵'.number_format($available, 2)
                    .' but this debit needs GH₵'.number_format($amount, 2).'.'
                );
            }

            $wallet->decrement('available_balance', $amount);

            return WalletTransactionService::recordAdminDebit($target->id, $amount, $admin->id, $note);
        });
    }

    /**
     * Debit seller available balance (locked). Used for pay-to-seller cancel clawbacks.
     *
     * @throws \RuntimeException when balance is insufficient
     */
    public static function debitAvailable(User $user, float $amount, string $insufficientMessage): Wallet
    {
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first()
            ?? static::ensure($user);

        $available = (float) $wallet->available_balance;
        if ($available + 0.0001 < $amount) {
            throw new \RuntimeException($insufficientMessage);
        }

        $wallet->decrement('available_balance', $amount);

        return $wallet->fresh();
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
