<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('buyer_cleared_at')->nullable()->after('seller_hidden_at');
            $table->timestamp('seller_cleared_at')->nullable()->after('buyer_cleared_at');
        });

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->timestamp('messages_cleared_at')->nullable()->after('hidden_at');
        });

        Schema::create('chat_clear_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_to')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            $table->index(['conversation_id', 'status']);
            $table->index(['requested_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_clear_requests');

        Schema::table('conversation_participants', function (Blueprint $table) {
            $table->dropColumn('messages_cleared_at');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['buyer_cleared_at', 'seller_cleared_at']);
        });
    }
};
