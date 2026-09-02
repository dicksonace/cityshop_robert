<?php

namespace App\Console\Commands;

use App\Services\WithdrawalPayoutService;
use Illuminate\Console\Command;

class ReconcilePaystackWithdrawalsCommand extends Command
{
    protected $signature = 'withdrawals:reconcile-paystack
                            {--limit=50 : Max processing withdrawals to check}';

    protected $description = 'Verify Paystack transfers and mark stuck withdrawals as Completed or Failed';

    public function handle(WithdrawalPayoutService $payouts): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $stats = $payouts->reconcilePendingPaystackTransfers($limit);

        $this->info("Checked {$stats['checked']} · paid {$stats['paid']} · failed {$stats['failed']} · still pending {$stats['skipped']}");

        return self::SUCCESS;
    }
}
