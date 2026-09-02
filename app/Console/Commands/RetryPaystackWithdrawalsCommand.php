<?php

namespace App\Console\Commands;

use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use App\Services\PaystackService;
use App\Services\WithdrawalPayoutService;
use Illuminate\Console\Command;

class RetryPaystackWithdrawalsCommand extends Command
{
    protected $signature = 'withdrawals:retry-paystack
                            {--id= : Retry a single withdrawal id}
                            {--limit=30 : Max withdrawals to retry}';

    protected $description = 'Retry pending/processing MoMo withdrawals that never reached Paystack (all networks).';

    public function handle(WithdrawalPayoutService $payouts, PaystackService $paystack): int
    {
        if (! $paystack->isConfigured()) {
            $this->error('Paystack is not configured.');

            return self::FAILURE;
        }

        $query = Withdrawal::query()
            ->whereIn('status', [WithdrawalStatus::Pending, WithdrawalStatus::Processing])
            ->where(function ($q): void {
                $q->whereNull('paystack_reference')->orWhere('paystack_reference', '');
            })
            ->orderBy('id');

        if ($id = $this->option('id')) {
            $query->whereKey((int) $id);
        } else {
            $query->limit(max(1, (int) $this->option('limit')));
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            $this->info('Nothing to retry.');

            return self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;

        foreach ($rows as $withdrawal) {
            try {
                $result = $payouts->process($withdrawal, null);
                $ok++;
                $this->line("WD-{$withdrawal->id} ({$withdrawal->network}): ".$result['message']);
            } catch (\Throwable $e) {
                $fail++;
                $withdrawal->update([
                    'failure_reason' => 'Paystack retry failed: '.$e->getMessage(),
                ]);
                $this->error("WD-{$withdrawal->id} ({$withdrawal->network}): ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Done. sent={$ok} failed={$fail}");

        return $fail > 0 && $ok === 0 ? self::FAILURE : self::SUCCESS;
    }
}
