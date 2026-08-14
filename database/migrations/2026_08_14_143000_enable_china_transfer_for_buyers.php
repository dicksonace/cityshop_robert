<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('china_transfer_settings')) {
            return;
        }

        DB::table('china_transfer_settings')->update(['enabled' => true]);
    }

    public function down(): void
    {
        // Leave availability as admin last saved it.
    }
};
