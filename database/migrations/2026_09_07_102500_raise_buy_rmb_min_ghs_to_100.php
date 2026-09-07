<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Raise live Buy RMB minimum to GH₵100 (admin can change anytime via settings).
        DB::table('china_transfer_rates')
            ->where('active', true)
            ->where('min_ghs', '<', 100)
            ->update(['min_ghs' => 100]);
    }

    public function down(): void
    {
        // No-op: previous mins varied and should not be restored blindly.
    }
};
