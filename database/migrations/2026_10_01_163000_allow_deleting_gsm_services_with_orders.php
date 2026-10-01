<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gsm_orders', function (Blueprint $table) {
            $table->dropForeign(['gsm_service_id']);
        });

        Schema::table('gsm_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('gsm_service_id')->nullable()->change();
        });

        Schema::table('gsm_orders', function (Blueprint $table) {
            $table->foreign('gsm_service_id')
                ->references('id')
                ->on('gsm_services')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gsm_orders', function (Blueprint $table) {
            $table->dropForeign(['gsm_service_id']);
        });

        Schema::table('gsm_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('gsm_service_id')->nullable(false)->change();
        });

        Schema::table('gsm_orders', function (Blueprint $table) {
            $table->foreign('gsm_service_id')
                ->references('id')
                ->on('gsm_services')
                ->restrictOnDelete();
        });
    }
};
