<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsm_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price_ghs', 12, 2);
            $table->string('currency', 8)->default('GHS');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('gsm_service_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gsm_service_id')->constrained('gsm_services')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->string('type', 20)->default('text');
            $table->boolean('required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('gsm_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gsm_service_id')->constrained('gsm_services')->restrictOnDelete();
            $table->string('service_name');
            $table->decimal('price_ghs', 12, 2);
            $table->string('status', 32);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->boolean('refunded')->default(false);
            $table->text('admin_result_note')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('gsm_order_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gsm_order_id')->constrained('gsm_orders')->cascadeOnDelete();
            $table->foreignId('gsm_service_field_id')->nullable()->constrained('gsm_service_fields')->nullOnDelete();
            $table->string('field_name');
            $table->string('field_label');
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('gsm_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gsm_order_id')->constrained('gsm_orders')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $now = now();

        $serviceId = DB::table('gsm_services')->insertGetId([
            'name' => 'MDM Remove (Infinix / Tecno / iTel)',
            'slug' => 'mdm-remove-infinix-tecno',
            'description' => 'Removes the MDM lock from eligible Tecno, Infinix and iTel devices. Submit the correct IMEI and your request is processed under the service terms.',
            'price_ghs' => 450.00,
            'currency' => 'GHS',
            'sort_order' => 1,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('gsm_service_fields')->insert([
            [
                'gsm_service_id' => $serviceId,
                'name' => 'imei',
                'label' => 'IMEI',
                'placeholder' => 'Enter 15-digit IMEI',
                'type' => 'text',
                'required' => true,
                'sort_order' => 1,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'gsm_service_id' => $serviceId,
                'name' => 'lock_screen_photo_link',
                'label' => 'Lock Screen Photo Link',
                'placeholder' => 'Paste photo URL',
                'type' => 'text',
                'required' => true,
                'sort_order' => 2,
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Category::updateOrCreate(
            ['slug' => 'gsm-tools'],
            [
                'name' => 'GSM Tools',
                'icon' => 'smartphone',
                'spec_schema' => null,
                'is_active' => true,
                'sort_order' => 22,
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('gsm_order_status_history');
        Schema::dropIfExists('gsm_order_field_values');
        Schema::dropIfExists('gsm_orders');
        Schema::dropIfExists('gsm_service_fields');
        Schema::dropIfExists('gsm_services');
        Category::where('slug', 'gsm-tools')->delete();
    }
};
