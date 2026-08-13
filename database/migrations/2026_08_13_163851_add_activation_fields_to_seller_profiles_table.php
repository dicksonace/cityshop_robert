<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->decimal('activation_fee_amount', 12, 2)->nullable()->after('cash_on_delivery_enabled');
            $table->timestamp('activation_prompted_at')->nullable()->after('activation_fee_amount');
            $table->timestamp('activation_paid_at')->nullable()->after('activation_prompted_at');
            $table->timestamp('activation_paid_until')->nullable()->after('activation_paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'activation_fee_amount',
                'activation_prompted_at',
                'activation_paid_at',
                'activation_paid_until',
            ]);
        });
    }
};
