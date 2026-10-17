<?php

namespace App\Console\Commands;

use App\Models\Withdrawal;
use App\Services\JunipayService;
use App\Services\WithdrawalPayoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * php artisan junipay:payout-status 66
 */
class JunipayPayoutStatusCommand extends Command
{
    protected $signature = 'junipay:payout-status {withdrawal : Withdrawal ID}';

    protected $description = 'Show what Junipay says about a withdrawal payout and apply it';

    public function handle(JunipayService $junipay, WithdrawalPayoutService $payouts): int
    {
        $withdrawal = Withdrawal::find((int) $this->argument('withdrawal'));
        if (! $withdrawal) {
            $this->error('Withdrawal not found.');

            return self::FAILURE;
        }

        $this->line("Withdrawal #{$withdrawal->id} · status {$withdrawal->status->value} · channel {$withdrawal->payout_channel}");
        $this->line('junipay_trans_id: '.($withdrawal->junipay_trans_id ?: '—'));
        $this->line('junipay_reference: '.($withdrawal->junipay_reference ?: '—'));
        $this->line('junipay_status: '.($withdrawal->junipay_status ?: '—'));

        foreach (array_filter([$withdrawal->junipay_trans_id, $withdrawal->junipay_reference]) as $id) {
            $raw = $junipay->rawStatus((string) $id);
            $this->newLine();
            $this->info("checktranstatus {$id} → HTTP {$raw['http']}");
            $this->line(json_encode($raw['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        Cache::forget('junipay-payout-check:'.$withdrawal->id);
        $after = $payouts->refreshJunipay($withdrawal);
        $this->newLine();
        $this->info('After applying: '.$after->status->value.' · junipay_status '.($after->junipay_status ?: '—'));

        return self::SUCCESS;
    }
}
