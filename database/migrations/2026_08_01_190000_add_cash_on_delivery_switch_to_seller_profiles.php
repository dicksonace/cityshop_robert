<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cash on delivery used to be offered on every order. It becomes the
     * seller's call, starting enabled so nothing changes for live stores.
     */
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->boolean('cash_on_delivery_enabled')->default(true)->after('accept_direct_payments');
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn('cash_on_delivery_enabled');
        });
    }
};
