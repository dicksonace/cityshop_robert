<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'china_rmb_enabled')) {
                $table->boolean('china_rmb_enabled')->default(false)->after('blocked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'china_rmb_enabled')) {
                $table->dropColumn('china_rmb_enabled');
            }
        });
    }
};
