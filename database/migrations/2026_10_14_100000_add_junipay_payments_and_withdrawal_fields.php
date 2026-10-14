<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('junipay_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 100)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->json('meta')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('redirect_url', 500)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('provider', 20)->nullable();
            $table->string('trans_id', 100)->nullable()->index();
            $table->string('message', 255)->nullable();
            $table->timestamps();
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('junipay_reference', 100)->nullable()->unique();
            $table->string('junipay_trans_id', 100)->nullable();
            $table->string('junipay_status', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropUnique(['junipay_reference']);
            $table->dropColumn(['junipay_reference', 'junipay_trans_id', 'junipay_status']);
        });

        Schema::dropIfExists('junipay_payments');
    }
};
