<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->boolean('manual_payout')->default(false)->after('payout_channel');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('manual_payout');
        });
    }
};
