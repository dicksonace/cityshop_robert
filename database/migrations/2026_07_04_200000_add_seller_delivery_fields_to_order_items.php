<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('vehicle_number')->nullable()->after('tracking_number');
            $table->string('driver_phone')->nullable()->after('vehicle_number');
            $table->string('package_image')->nullable()->after('driver_phone');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['vehicle_number', 'driver_phone', 'package_image']);
        });
    }
};
