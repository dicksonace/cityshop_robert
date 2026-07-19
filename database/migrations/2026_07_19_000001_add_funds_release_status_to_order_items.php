<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('funds_release_status')->nullable()->after('refund_status');
            $table->text('funds_release_notes')->nullable()->after('funds_release_status');
            $table->foreignId('funds_reviewed_by')->nullable()->after('funds_release_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('funds_released_at')->nullable()->after('funds_reviewed_by');
            $table->index('funds_release_status');
        });

        // Legacy flow released funds on buyer confirm — mark those complete.
        DB::table('order_items')
            ->where('status', 'delivered')
            ->whereNull('funds_release_status')
            ->update([
                'funds_release_status' => 'released',
                'funds_released_at' => now(),
            ]);

        DB::table('order_items')
            ->whereIn('status', ['cancelled', 'refunded'])
            ->whereNull('funds_release_status')
            ->update(['funds_release_status' => 'not_applicable']);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('funds_reviewed_by');
            $table->dropIndex(['funds_release_status']);
            $table->dropColumn([
                'funds_release_status',
                'funds_release_notes',
                'funds_released_at',
            ]);
        });
    }
};
