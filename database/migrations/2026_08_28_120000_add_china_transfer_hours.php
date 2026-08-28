<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('china_transfer_settings', function (Blueprint $table) {
            $table->time('transfer_open_time')->default('04:30:00')->after('instructions');
            $table->time('transfer_close_time')->default('17:00:00')->after('transfer_open_time');
        });
    }

    public function down(): void
    {
        Schema::table('china_transfer_settings', function (Blueprint $table) {
            $table->dropColumn(['transfer_open_time', 'transfer_close_time']);
        });
    }
};
