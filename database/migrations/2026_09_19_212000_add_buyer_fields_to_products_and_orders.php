<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'buyer_fields')) {
                $table->json('buyer_fields')->nullable()->after('specifications');
            }
        });

        Schema::table('cart_items', function (Blueprint $table) {
            if (! Schema::hasColumn('cart_items', 'buyer_field_values')) {
                $table->json('buyer_field_values')->nullable()->after('quantity');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'buyer_field_values')) {
                $table->json('buyer_field_values')->nullable()->after('product_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'buyer_fields')) {
                $table->dropColumn('buyer_fields');
            }
        });

        Schema::table('cart_items', function (Blueprint $table) {
            if (Schema::hasColumn('cart_items', 'buyer_field_values')) {
                $table->dropColumn('buyer_field_values');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_items', 'buyer_field_values')) {
                $table->dropColumn('buyer_field_values');
            }
        });
    }
};
