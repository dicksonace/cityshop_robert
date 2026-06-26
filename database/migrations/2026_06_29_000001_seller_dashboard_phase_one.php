<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('momo');
            $table->string('network');
            $table->string('account_number', 30);
            $table->string('account_name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->foreignId('payout_method_id')->nullable()->after('user_id')->constrained('seller_payout_methods')->nullOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('condition')->default('new')->after('brand');
            $table->decimal('delivery_fee', 12, 2)->nullable()->after('free_shipping');
            $table->unsignedSmallInteger('delivery_days')->nullable()->after('delivery_fee');
            $table->unsignedSmallInteger('low_stock_alert')->default(5)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['condition', 'delivery_fee', 'delivery_days', 'low_stock_alert']);
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payout_method_id');
        });

        Schema::dropIfExists('seller_payout_methods');
    }
};
