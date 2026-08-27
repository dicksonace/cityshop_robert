<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('rmb_balance', 14, 2)->default(0)->after('withdrawn_amount');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('currency', 8)->default('GHS')->after('amount');
            $table->index(['user_id', 'currency', 'created_at']);
        });

        Schema::table('china_transfers', function (Blueprint $table) {
            $table->string('funding_source', 32)->default('external')->after('payment_method_id');
            $table->boolean('rmb_wallet_refunded')->default(false)->after('funding_source');
        });

        Schema::create('wallet_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 32); // ghs_to_rmb | rmb_to_ghs
            $table->decimal('amount_ghs', 14, 2);
            $table->decimal('amount_rmb', 14, 2);
            $table->decimal('rate', 14, 6);
            $table->string('reference')->unique();
            $table->string('status', 32)->default('approved');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_conversions');

        Schema::table('china_transfers', function (Blueprint $table) {
            $table->dropColumn(['funding_source', 'rmb_wallet_refunded']);
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'currency', 'created_at']);
            $table->dropColumn('currency');
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn('rmb_balance');
        });
    }
};
