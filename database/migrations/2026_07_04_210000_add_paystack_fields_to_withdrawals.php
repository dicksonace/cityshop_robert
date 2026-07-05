<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('paystack_recipient_code')->nullable()->after('network');
            $table->string('paystack_transfer_code')->nullable()->after('paystack_recipient_code');
            $table->string('paystack_reference')->nullable()->unique()->after('paystack_transfer_code');
            $table->string('paystack_status')->nullable()->after('paystack_reference');
            $table->text('failure_reason')->nullable()->after('rejection_reason');
        });

        Schema::table('seller_payout_methods', function (Blueprint $table) {
            $table->string('paystack_recipient_code')->nullable()->after('account_name');
        });
    }

    public function down(): void
    {
        Schema::table('seller_payout_methods', function (Blueprint $table) {
            $table->dropColumn('paystack_recipient_code');
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn([
                'paystack_recipient_code',
                'paystack_transfer_code',
                'paystack_reference',
                'paystack_status',
                'failure_reason',
            ]);
        });
    }
};
