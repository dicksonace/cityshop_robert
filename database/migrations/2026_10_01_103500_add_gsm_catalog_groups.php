<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsm_service_groups', function (Blueprint $table) {
            $table->id();
            $table->string('service_type', 20);
            $table->string('name');
            $table->string('slug');
            $table->string('image')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['service_type', 'active', 'sort_order']);
        });

        Schema::table('gsm_services', function (Blueprint $table) {
            $table->foreignId('gsm_service_group_id')->nullable()->after('service_type')->constrained('gsm_service_groups')->nullOnDelete();
            $table->string('image')->nullable()->after('description');
            $table->text('overview')->nullable()->after('image');
            $table->json('features')->nullable()->after('overview');
            $table->text('what_to_send')->nullable()->after('features');
            $table->string('eta_label', 40)->nullable()->after('what_to_send');
            $table->boolean('allow_quantity')->default(true)->after('eta_label');
            $table->unsignedInteger('min_qty')->default(1)->after('allow_quantity');
            $table->unsignedInteger('max_qty')->default(1000)->after('min_qty');
        });

        Schema::table('gsm_orders', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->after('service_name');
            $table->decimal('unit_price_ghs', 12, 2)->nullable()->after('quantity');
            $table->string('contact_email')->nullable()->after('unit_price_ghs');
        });
    }

    public function down(): void
    {
        Schema::table('gsm_orders', function (Blueprint $table) {
            $table->dropColumn(['quantity', 'unit_price_ghs', 'contact_email']);
        });
        Schema::table('gsm_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gsm_service_group_id');
            $table->dropColumn(['image', 'overview', 'features', 'what_to_send', 'eta_label', 'allow_quantity', 'min_qty', 'max_qty']);
        });
        Schema::dropIfExists('gsm_service_groups');
    }
};
