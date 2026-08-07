<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('buyer_hidden_at')->nullable()->after('last_message_at');
            $table->timestamp('seller_hidden_at')->nullable()->after('buyer_hidden_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['buyer_hidden_at', 'seller_hidden_at']);
        });
    }
};
