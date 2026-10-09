<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_order_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $files = [
            'slider/hero-2.jpg',
            'slider/hero-4.jpg',
            'slider/hero-6.jpg',
            'slider/rail-1.png',
            'slider/tile-2.png',
            'slider/tile-3.png',
            'slider/tile-4.png',
            'slider/tile-6.png',
        ];

        foreach ($files as $index => $file) {
            if (! is_file(public_path($file))) {
                continue;
            }
            DB::table('place_order_slides')->insert([
                'image' => $file,
                'sort_order' => $index,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('place_order_slides');
    }
};
