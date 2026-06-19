<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('icon')->nullable()->after('slug');
            $table->json('spec_schema')->nullable()->after('icon');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->json('specifications')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('specifications');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['icon', 'spec_schema']);
        });
    }
};
