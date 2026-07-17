<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('cancellation_code')->nullable()->after('rejection_reason');
            $table->string('cancelled_by')->nullable()->after('cancellation_code'); // seller|admin
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->string('refund_status')->nullable()->after('cancelled_at'); // completed|not_applicable|failed
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['cancellation_code', 'cancelled_by', 'cancelled_at', 'refund_status']);
        });
    }
};
