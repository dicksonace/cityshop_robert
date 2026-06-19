<?php

namespace App\Console\Commands;

use App\Services\ReviewService;
use Illuminate\Console\Command;

class SyncRatingsCommand extends Command
{
    protected $signature = 'ratings:sync';

    protected $description = 'Recalculate product and seller ratings from customer reviews';

    public function handle(): int
    {
        $this->info('Syncing ratings from reviews...');

        $result = ReviewService::syncAllRatings();

        $this->info("Updated {$result['products']} products and {$result['sellers']} sellers.");

        return self::SUCCESS;
    }
}
