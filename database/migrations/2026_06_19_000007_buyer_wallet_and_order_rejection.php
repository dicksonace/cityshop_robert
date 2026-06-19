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
            $table->text('rejection_reason')->nullable()->after('status');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        if (Schema::hasColumn('withdrawals', 'seller_id')) {
            foreach (DB::table('withdrawals')->get() as $withdrawal) {
                DB::table('withdrawals')
                    ->where('id', $withdrawal->id)
                    ->update(['user_id' => $withdrawal->seller_id]);
            }

            Schema::table('withdrawals', function (Blueprint $table) {
                $table->dropForeign(['seller_id']);
                $table->dropColumn('seller_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
        });

        DB::table('withdrawals')->update(['seller_id' => DB::raw('user_id')]);

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
