<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'gsm_tools_enabled')) {
                $table->boolean('gsm_tools_enabled')->default(false)->after('china_rmb_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'gsm_tools_enabled')) {
                $table->dropColumn('gsm_tools_enabled');
            }
        });
    }
};
