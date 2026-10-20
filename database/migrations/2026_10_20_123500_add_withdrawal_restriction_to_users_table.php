<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'withdrawal_restricted')) {
                $table->boolean('withdrawal_restricted')->default(false)->after('china_rmb_enabled');
            }
            if (! Schema::hasColumn('users', 'withdrawal_restriction_note')) {
                $table->text('withdrawal_restriction_note')->nullable()->after('withdrawal_restricted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'withdrawal_restriction_note')) {
                $table->dropColumn('withdrawal_restriction_note');
            }
            if (Schema::hasColumn('users', 'withdrawal_restricted')) {
                $table->dropColumn('withdrawal_restricted');
            }
        });
    }
};
