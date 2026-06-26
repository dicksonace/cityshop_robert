<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_registration_invites', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('email')->nullable();
            $table->string('name')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['token', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_registration_invites');
    }
};
