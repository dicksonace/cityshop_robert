<?php

namespace App\Services;

use App\Enums\WalletTransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletConversion;
use App\Models\WalletTransaction;
use App\Notifications\WalletConversionNotification;
use App\Notifications\WalletFundedNotification;
use App\Notifications\WalletTransferReceivedNotification;
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
                'rmb_balance' => 0,
            ]
        );
    }

    public static function adminCredit(User $target, float $amount, User $admin, ?string $note = null): WalletTransaction
    {
        $tx = DB::transaction(function () use ($target, $amount, $admin, $note) {
            $wallet = Wallet::where('user_id', $target->id)->lockForUpdate()->first()
                ?? static::ensure($target);

            $wallet->increment('available_balance', $amount);

            return WalletTransactionService::recordAdminCredit($target->id, $amount, $admin->id, $note);
        });

        try {
            $target->notify(new WalletFundedNotification(
                $amount,
                'admin',
                'ADMIN-'.$tx->id,
                (float) Wallet::query()->where('user_id', $target->id)->value('available_balance'),
                now('Africa/Accra'),
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return $tx;
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
        $available = DB::transaction(function () use ($userId, $amount, $reference, $method) {
            if (WalletTransaction::where('reference', $reference)->exists()) {
                return null;
            }

            $wallet = Wallet::where('user_id', $userId)->lockForUpdate()->first();

            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id' => $userId,
                    'available_balance' => 0,
                    'pending_balance' => 0,
                    'total_earnings' => 0,
                    'withdrawn_amount' => 0,
                    'rmb_balance' => 0,
                ]);
            }

            $wallet->increment('available_balance', $amount);
            WalletTransactionService::recordFundAdded($userId, $amount, $method, $reference);

            return (float) $wallet->available_balance;
        });

        if ($available !== null) {
            $user = User::query()->find($userId);
            try {
                $user?->notify(new WalletFundedNotification(
                    $amount,
                    $method,
                    $reference,
                    (float) $available,
                    now('Africa/Accra'),
                ));
            } catch (\Throwable $e) {
                report($e);
            }

            // Buyer still gets WalletFundedNotification above.
            // No admin SMS/email on successful credits: Paystack MoMo/card auto-pays
            // were flooding admins; manual deposits already alert via depositProof().
        }

        return $available !== null;
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

        $result = DB::transaction(function () use ($from, $to, $amount, $note) {
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
            $recipientAvailable = (float) ($recipientWallet->fresh()?->available_balance ?? 0);

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
                'recipient_available' => $recipientAvailable,
            ];
        });

        try {
            $to->notify(new WalletTransferReceivedNotification(
                $from,
                (float) $result['amount'],
                $result['reference'],
                $result['note'],
                (float) $result['recipient_available'],
                now('Africa/Accra'),
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return $result;
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

    /**
     * Instant GHS ↔ RMB convert using published China (buy) / Sell rates.
     * Server recomputes the counterpart amount — client result is ignored.
     *
     * Caller must assert KYC + payment PIN + velocity before calling.
     *
     * @param  array{ip_address?: ?string, user_agent?: ?string}  $meta
     * @return array{
     *     direction: string,
     *     amount_ghs: float,
     *     amount_rmb: float,
     *     rate: float,
     *     reference: string,
     *     available_balance: float,
     *     rmb_balance: float,
     *     available_before: float,
     *     rmb_before: float,
     *     message: string
     * }
     */
    public static function convert(User $user, string $direction, float $amount, array $meta = []): array
    {
        $quote = static::convertQuote($direction, $amount);

        $result = DB::transaction(function () use ($user, $direction, $quote, $meta) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first()
                ?? static::ensure($user);

            $availableBefore = (float) $wallet->available_balance;
            $rmbBefore = (float) $wallet->rmb_balance;
            $amountGhs = $quote['amount_ghs'];
            $amountRmb = $quote['amount_rmb'];
            $rate = $quote['rate'];
            $reference = 'EXCHANGE-'.strtoupper(bin2hex(random_bytes(6)));

            if ($direction === 'ghs_to_rmb') {
                if ($availableBefore + 0.0001 < $amountGhs) {
                    throw new \RuntimeException(
                        'Insufficient GHS balance. You have GH₵'.number_format($availableBefore, 2).'.'
                    );
                }

                $wallet->decrement('available_balance', $amountGhs);
                $wallet->increment('rmb_balance', $amountRmb);

                WalletTransactionService::record(
                    userId: $user->id,
                    type: WalletTransactionType::ConvertGhsToRmb,
                    amount: -1 * $amountGhs,
                    description: 'Exchanged GH₵'.number_format($amountGhs, 2).' to ¥'.number_format($amountRmb, 2),
                    reference: $reference,
                    currency: 'GHS',
                );
                WalletTransactionService::record(
                    userId: $user->id,
                    type: WalletTransactionType::ConvertGhsToRmb,
                    amount: $amountRmb,
                    description: 'Received ¥'.number_format($amountRmb, 2).' from GH₵'.number_format($amountGhs, 2),
                    reference: $reference,
                    currency: 'RMB',
                );

                $message = 'Exchange successful! GH₵'.number_format($amountGhs, 2).' → ¥'.number_format($amountRmb, 2);
            } else {
                if ($rmbBefore + 0.0001 < $amountRmb) {
                    throw new \RuntimeException(
                        'Insufficient RMB balance. You have ¥'.number_format($rmbBefore, 2).'.'
                    );
                }

                $wallet->decrement('rmb_balance', $amountRmb);
                $wallet->increment('available_balance', $amountGhs);

                WalletTransactionService::record(
                    userId: $user->id,
                    type: WalletTransactionType::ConvertRmbToGhs,
                    amount: -1 * $amountRmb,
                    description: 'Exchanged ¥'.number_format($amountRmb, 2).' to GH₵'.number_format($amountGhs, 2),
                    reference: $reference,
                    currency: 'RMB',
                );
                WalletTransactionService::record(
                    userId: $user->id,
                    type: WalletTransactionType::ConvertRmbToGhs,
                    amount: $amountGhs,
                    description: 'Received GH₵'.number_format($amountGhs, 2).' from ¥'.number_format($amountRmb, 2),
                    reference: $reference,
                    currency: 'GHS',
                );

                $message = 'Exchange successful! ¥'.number_format($amountRmb, 2).' → GH₵'.number_format($amountGhs, 2);
            }

            WalletConversion::create([
                'user_id' => $user->id,
                'direction' => $direction,
                'amount_ghs' => $amountGhs,
                'amount_rmb' => $amountRmb,
                'rate' => $rate,
                'reference' => $reference,
                'status' => 'approved',
                'ip_address' => $meta['ip_address'] ?? null,
                'user_agent' => $meta['user_agent'] ?? null,
            ]);

            $wallet->refresh();

            return [
                'direction' => $direction,
                'amount_ghs' => $amountGhs,
                'amount_rmb' => $amountRmb,
                'rate' => $rate,
                'reference' => $reference,
                'available_balance' => (float) $wallet->available_balance,
                'rmb_balance' => (float) $wallet->rmb_balance,
                'available_before' => $availableBefore,
                'rmb_before' => $rmbBefore,
                'message' => $message,
            ];
        });

        try {
            AppNotificationService::send(
                $user,
                'wallet_conversion',
                'Exchange completed',
                $result['message'],
                [
                    'reference' => $result['reference'],
                    'direction' => $result['direction'],
                    'amount_ghs' => $result['amount_ghs'],
                    'amount_rmb' => $result['amount_rmb'],
                    'url' => '/wallet/china-rmb',
                ],
            );
            $user->notify(new WalletConversionNotification(
                $result['direction'],
                $result['amount_ghs'],
                $result['amount_rmb'],
                $result['reference'],
                $result['available_balance'],
                $result['rmb_balance'],
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return $result;
    }

    /**
     * @return array{
     *     direction: string,
     *     amount: float,
     *     amount_ghs: float,
     *     amount_rmb: float,
     *     rate: float,
     *     rate_label: string,
     *     result_label: string
     * }
     */
    public static function convertQuote(string $direction, float $amount): array
    {
        $direction = $direction === 'rmb_to_ghs' ? 'rmb_to_ghs' : 'ghs_to_rmb';
        $amount = round($amount, 2);

        if ($amount < 1) {
            throw new \RuntimeException('Minimum convert amount is 1.00.');
        }

        if ($direction === 'ghs_to_rmb') {
            $china = app(ChinaTransferService::class);
            $rateRow = $china->currentRate();
            if (! $rateRow) {
                throw new \RuntimeException('GHS → RMB convert is not available. Admin has not published a buy rate yet.');
            }
            $ghsPerRmb = (float) $rateRow->ghs_per_rmb;
            if ($ghsPerRmb <= 0) {
                throw new \RuntimeException('The published GHS → RMB rate is invalid.');
            }
            $amountGhs = $amount;
            $amountRmb = round($amountGhs / $ghsPerRmb, 2);
            if ($amountRmb < 0.01) {
                throw new \RuntimeException('Converted RMB amount is too small. Enter a larger GHS amount.');
            }

            return [
                'direction' => $direction,
                'amount' => $amountGhs,
                'amount_ghs' => $amountGhs,
                'amount_rmb' => $amountRmb,
                'rate' => $ghsPerRmb,
                'rate_label' => '1 RMB = GH₵'.number_format($ghsPerRmb, 4),
                'result_label' => '¥'.number_format($amountRmb, 2),
            ];
        }

        $sell = app(SellRmbService::class);
        $rateRow = $sell->currentRate();
        if (! $rateRow) {
            throw new \RuntimeException('RMB → GHS convert is not available. Admin has not published a sell rate yet.');
        }
        $ghsPerRmb = $rateRow->ghsPerRmb();
        if ($ghsPerRmb <= 0) {
            throw new \RuntimeException('The published RMB → GHS rate is invalid.');
        }
        $amountRmb = $amount;
        $amountGhs = round($amountRmb * $ghsPerRmb, 2);
        if ($amountGhs < 0.01) {
            throw new \RuntimeException('Converted GHS amount is too small. Enter a larger RMB amount.');
        }

        return [
            'direction' => $direction,
            'amount' => $amountRmb,
            'amount_ghs' => $amountGhs,
            'amount_rmb' => $amountRmb,
            'rate' => $ghsPerRmb,
            'rate_label' => '1 RMB = GH₵'.number_format($ghsPerRmb, 4),
            'result_label' => 'GH₵'.number_format($amountGhs, 2),
        ];
    }

    public static function adminCreditRmb(User $target, float $amount, User $admin, ?string $note = null): WalletTransaction
    {
        return DB::transaction(function () use ($target, $amount, $admin, $note) {
            $wallet = Wallet::where('user_id', $target->id)->lockForUpdate()->first()
                ?? static::ensure($target);

            $wallet->increment('rmb_balance', $amount);

            return WalletTransactionService::recordAdminRmbCredit($target->id, $amount, $admin->id, $note);
        });
    }

    /**
     * @throws \RuntimeException when balance is insufficient
     */
    public static function adminDebitRmb(User $target, float $amount, User $admin, ?string $note = null): WalletTransaction
    {
        return DB::transaction(function () use ($target, $amount, $admin, $note) {
            $wallet = Wallet::where('user_id', $target->id)->lockForUpdate()->first()
                ?? static::ensure($target);

            $rmb = (float) $wallet->rmb_balance;
            if ($rmb + 0.0001 < $amount) {
                throw new \RuntimeException(
                    'Insufficient RMB balance. '.$target->name.' has ¥'.number_format($rmb, 2)
                    .' but this debit needs ¥'.number_format($amount, 2).'.'
                );
            }

            $wallet->decrement('rmb_balance', $amount);

            return WalletTransactionService::recordAdminRmbDebit($target->id, $amount, $admin->id, $note);
        });
    }

    /**
     * Debit RMB balance (caller must already be inside a DB transaction with locks as needed).
     *
     * @throws \RuntimeException when balance is insufficient
     */
    public static function debitRmb(User $user, float $amount, string $insufficientMessage): Wallet
    {
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first()
            ?? static::ensure($user);

        $rmb = (float) $wallet->rmb_balance;
        if ($rmb + 0.0001 < $amount) {
            throw new \RuntimeException($insufficientMessage);
        }

        $wallet->decrement('rmb_balance', $amount);

        return $wallet->fresh();
    }

    public static function creditRmb(User $user, float $amount): Wallet
    {
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first()
            ?? static::ensure($user);

        $wallet->increment('rmb_balance', $amount);

        return $wallet->fresh();
    }
}
