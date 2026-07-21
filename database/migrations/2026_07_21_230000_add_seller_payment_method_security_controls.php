<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->timestamp('payment_methods_locked_at')->nullable()->after('accept_direct_payments');
            $table->foreignId('payment_methods_locked_by')->nullable()->after('payment_methods_locked_at')
                ->constrained('users')->nullOnDelete();
            $table->text('payment_methods_lock_reason')->nullable()->after('payment_methods_locked_by');
        });

        Schema::table('seller_payment_methods', function (Blueprint $table) {
            $table->timestamp('disabled_at')->nullable()->after('is_default');
            $table->foreignId('disabled_by')->nullable()->after('disabled_at')
                ->constrained('users')->nullOnDelete();
            $table->text('disabled_reason')->nullable()->after('disabled_by');
        });
    }

    public function down(): void
    {
        Schema::table('seller_payment_methods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disabled_by');
            $table->dropColumn(['disabled_at', 'disabled_reason']);
        });

        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_methods_locked_by');
            $table->dropColumn(['payment_methods_locked_at', 'payment_methods_lock_reason']);
        });
    }
};
