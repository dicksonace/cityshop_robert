<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_conversions', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('status');
            $table->string('user_agent', 512)->nullable()->after('ip_address');
        });

        Schema::table('china_transfers', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('rmb_wallet_refunded');
            $table->string('user_agent', 512)->nullable()->after('ip_address');
        });

        Schema::table('china_transfer_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_converts_per_day')->default(30)->after('instructions');
            $table->decimal('max_rmb_out_per_day', 14, 2)->nullable()->after('max_converts_per_day');
            $table->decimal('max_rmb_out_per_month', 14, 2)->nullable()->after('max_rmb_out_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('china_transfer_settings', function (Blueprint $table) {
            $table->dropColumn(['max_converts_per_day', 'max_rmb_out_per_day', 'max_rmb_out_per_month']);
        });

        Schema::table('china_transfers', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });

        Schema::table('wallet_conversions', function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }
};
