<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->boolean('is_group')->default(false)->after('product_id');
            $table->string('name')->nullable()->after('is_group');
            $table->foreignId('created_by')->nullable()->after('name')->constrained('users')->nullOnDelete();
        });

        // Direct uniqueness is enforced in ChatService; groups need many rows.
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropUnique(['buyer_id', 'seller_id']);
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index(['user_id', 'hidden_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['is_group', 'name', 'created_by']);
            $table->unique(['buyer_id', 'seller_id']);
        });
    }
};
