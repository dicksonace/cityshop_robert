<?php

namespace App\Console\Commands;

use App\Services\PlatformSettings;
use Illuminate\Console\Command;

class UnlockFlutterwaveCommand extends Command
{
    protected $signature = 'cityshop:unlock-flutterwave
                            {--lock : Lock Flutterwave collections instead}
                            {--force : Skip confirmation}';

    protected $description = 'Unlock (or lock) Flutterwave checkout and wallet recharge';

    public function handle(): int
    {
        $lock = (bool) $this->option('lock');
        $label = $lock ? 'lock' : 'unlock';

        if (! $this->option('force') && ! $this->confirm("{$label} Flutterwave collections?", true)) {
            $this->warn('Cancelled.');

            return self::SUCCESS;
        }

        PlatformSettings::saveFlutterwavePaymentsSettings(['locked' => $lock]);

        $this->info($lock
            ? 'Flutterwave is locked. Buyers will not see Flutterwave checkout or recharge.'
            : 'Flutterwave is unlocked for checkout and wallet recharge.');

        return self::SUCCESS;
    }
}
