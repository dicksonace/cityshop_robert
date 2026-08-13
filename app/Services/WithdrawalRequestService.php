<?php

namespace App\Services;

use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Notifications\AdminWithdrawalRequestedNotification;
use App\Notifications\WithdrawalRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared create + optional Paystack auto-payout for buyer/seller/API withdraw.
 */
class WithdrawalRequestService
{
    public function __construct(private WithdrawalPayoutService $payouts) {}

    /**
     * @param  array{
     *   amount: float,
     *   payout_type: string,
     *   momo_number: string,
     *   account_name: string,
     *   network: string,
     *   payout_method_id?: int|null
     * }  $data
     * @return array{withdrawal: Withdrawal, wallet: Wallet, message: string}
     */
    public function submit(User $user, array $data): array
    {
        $amount = round((float) $data['amount'], 2);
        $payoutType = ($data['payout_type'] ?? '') === 'bank' ? 'bank' : 'momo';
        $fee = PlatformSettings::feeForWithdrawal($amount, $payoutType);
        $totalDebit = round($amount + $fee, 2);
        $auto = PlatformSettings::autoPaystackWithdrawEnabled();

        $result = DB::transaction(function () use ($user, $data, $amount, $fee, $totalDebit, $payoutType) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first()
                ?? WalletService::ensure($user);

            if ($totalDebit > (float) $wallet->available_balance) {
                $message = $fee > 0
                    ? 'Insufficient available balance. Needs GH₵'.number_format($totalDebit, 2)
                        .' (incl. GH₵'.number_format($fee, 2).' fee).'
                    : 'Insufficient available balance.';

                throw new \InvalidArgumentException($message);
            }

            $withdrawal = Withdrawal::create([
                'user_id' => $user->id,
                'payout_method_id' => $data['payout_method_id'] ?? null,
                'amount' => $amount,
                'fee' => $fee,
                'momo_number' => $data['momo_number'],
                'account_name' => $data['account_name'],
                'network' => $data['network'],
                'payout_channel' => $payoutType,
                'status' => WithdrawalStatus::Pending,
            ]);

            $wallet->decrement('available_balance', $totalDebit);
            WalletTransactionService::recordWithdrawal($withdrawal);

            return [
                'withdrawal' => $withdrawal->fresh(),
                'wallet' => $wallet->fresh(),
            ];
        });

        $message = $auto
            ? 'Withdrawal submitted. Paystack is sending your payout automatically.'
            : 'Withdrawal request submitted. Usually processed within 15 minutes and sometimes instant.';

        try {
            $user->notify(new WithdrawalRequestedNotification($result['withdrawal']));
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            AdminNotifier::notify(new AdminWithdrawalRequestedNotification(
                $result['withdrawal']->loadMissing('user'),
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        if ($auto) {
            try {
                $payout = $this->payouts->processAuto($result['withdrawal']);
                $message = $payout['message'] ?: $message;
                $result['withdrawal'] = $result['withdrawal']->fresh();
            } catch (\Throwable $e) {
                Log::warning('Auto Paystack withdrawal deferred to admin', [
                    'withdrawal_id' => $result['withdrawal']->id,
                    'error' => $e->getMessage(),
                ]);
                $message = 'Withdrawal submitted. Payout will be completed shortly.';
            }
        }

        return [
            'withdrawal' => $result['withdrawal'],
            'wallet' => $result['wallet']->fresh(),
            'message' => $message,
        ];
    }
}
