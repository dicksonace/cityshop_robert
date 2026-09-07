<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class AppStoreReviewBuyerSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('cityshop:ensure-app-review-buyer');
    }
}
