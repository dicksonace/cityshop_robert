<?php

namespace App\Services;

use App\Enums\WalletTransactionType;
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

    /**
     * Instant peer-to-peer transfer in GHS between CityShop wallets.
     *
     * @return array{reference: string, amount: float, note: ?string}
     *
     * @throws \RuntimeException when balance is insufficient or users are invalid
     */
    public static function transfer(User $from, User $to, float $amount, ?string $note = null): array
    {
        if ($from->id === $to->id) {
            throw new \RuntimeException('You cannot transfer money to yourself.');
        }

        if (UserBlockService::isBlockedEitherWay($from, $to)) {
            throw new \RuntimeException('Transfers are blocked between these accounts.');
        }

        if ($amount < 1) {
            throw new \RuntimeException('Minimum transfer is GH₵1.00.');
        }

        if ($amount > 50000) {
            throw new \RuntimeException('Maximum transfer is GH₵50,000.00 per send.');
        }

        $note = $note !== null ? trim($note) : null;
        if ($note === '') {
            $note = null;
        }
        if ($note !== null && mb_strlen($note) > 120) {
            throw new \RuntimeException('Note must be 120 characters or fewer.');
        }

        return DB::transaction(function () use ($from, $to, $amount, $note) {
            static::ensure($from);
            static::ensure($to);

            // Lock in stable id order to avoid deadlocks on concurrent transfers.
            $ids = [$from->id, $to->id];
            sort($ids);

            $wallets = Wallet::query()
                ->whereIn('user_id', $ids)
                ->orderBy('user_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('user_id');

            $senderWallet = $wallets->get($from->id);
            $recipientWallet = $wallets->get($to->id);

            if (! $senderWallet || ! $recipientWallet) {
                throw new \RuntimeException('Could not load wallets for this transfer.');
            }

            $available = (float) $senderWallet->available_balance;
            if ($available + 0.0001 < $amount) {
                throw new \RuntimeException(
                    'Insufficient wallet balance. You have GH₵'.number_format($available, 2)
                    .' but this transfer needs GH₵'.number_format($amount, 2).'.'
                );
            }

            $reference = 'TRF-'.strtoupper(bin2hex(random_bytes(6)));

            $senderWallet->decrement('available_balance', $amount);
            $recipientWallet->increment('available_balance', $amount);

            $noteSuffix = $note ? " — {$note}" : '';

            WalletTransactionService::record(
                userId: $from->id,
                type: WalletTransactionType::TransferOut,
                amount: -1 * $amount,
                description: 'Transfer to '.self::partyLabel($to).$noteSuffix,
                reference: $reference,
            );

            WalletTransactionService::record(
                userId: $to->id,
                type: WalletTransactionType::TransferIn,
                amount: $amount,
                description: 'Transfer from '.self::partyLabel($from).$noteSuffix,
                reference: $reference,
            );

            return [
                'reference' => $reference,
                'amount' => round($amount, 2),
                'note' => $note,
                'currency' => 'GHS',
            ];
        });
    }

    /** Name plus Tel line for ledger / statement details, e.g. "Robert Asare Tel 0248620718". */
    public static function partyLabel(User $user): string
    {
        $name = trim((string) $user->name);
        if ($name === '') {
            $name = 'CityShop user';
        }

        $mobile = trim((string) ($user->mobile ?? ''));
        if ($mobile === '') {
            return $name;
        }

        return "{$name} Tel {$mobile}";
    }
}
