<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Products publish live on create — clear any legacy pending queue.
        DB::table('products')
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'rejection_reason' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Irreversible: pending queue is retired.
    }
};
