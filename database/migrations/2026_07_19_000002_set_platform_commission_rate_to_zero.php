<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('platform_settings')->updateOrInsert(
            ['key' => 'commission_rate'],
            ['value' => '0', 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget('platform_setting.commission_rate');
    }

    public function down(): void
    {
        DB::table('platform_settings')->updateOrInsert(
            ['key' => 'commission_rate'],
            ['value' => '10', 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget('platform_setting.commission_rate');
    }
};
