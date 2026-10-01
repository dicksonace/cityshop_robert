<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_two_factor_enabled')->default(false)->after('password');
            $table->text('totp_secret')->nullable()->after('email_two_factor_enabled');
            $table->timestamp('totp_confirmed_at')->nullable()->after('totp_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_two_factor_enabled', 'totp_secret', 'totp_confirmed_at']);
        });
    }
};
