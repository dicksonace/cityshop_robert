<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->string('order_sms_mobile_1', 20)->nullable()->after('cash_on_delivery_enabled');
            $table->string('order_sms_mobile_2', 20)->nullable()->after('order_sms_mobile_1');
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn(['order_sms_mobile_1', 'order_sms_mobile_2']);
        });
    }
};
