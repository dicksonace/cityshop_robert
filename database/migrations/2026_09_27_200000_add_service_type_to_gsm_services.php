<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gsm_services', function (Blueprint $table) {
            $table->string('service_type', 20)->default('imei')->after('slug');
        });

        DB::table('gsm_service_fields')
            ->where('name', 'lock_screen_photo_link')
            ->update([
                'type' => 'image',
                'label' => 'Picture on sign-in page',
                'placeholder' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('gsm_services', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
