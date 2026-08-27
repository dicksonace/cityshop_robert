<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('china_transfer_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('channel', 20)->default('alipay');
            $table->text('instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('china_transfer_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('ghs_per_rmb', 12, 4);
            $table->string('fee_mode', 12)->default('flat');
            $table->decimal('fee_value', 12, 2)->default(0);
            $table->decimal('min_ghs', 12, 2)->default(50);
            $table->decimal('max_ghs', 12, 2)->default(50000);
            $table->decimal('daily_max_ghs', 12, 2)->nullable();
            $table->decimal('monthly_max_ghs', 12, 2)->nullable();
            $table->unsignedInteger('max_per_day')->nullable();
            $table->decimal('approval_above_ghs', 12, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('china_transfer_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('momo');
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('network')->nullable();
            $table->text('instructions')->nullable();
            $table->string('qr_path')->nullable();
            $table->boolean('proof_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('china_transfer_form_fields', function (Blueprint $table) {
            $table->id();
            $table->string('group', 20)->default('recipient');
            $table->string('type', 20);
            $table->string('name', 80);
            $table->string('label');
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(true);
            $table->json('options')->nullable();
            $table->json('file_types')->nullable();
            $table->unsignedInteger('max_size_kb')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('china_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->decimal('ghs_amount', 12, 2);
            $table->decimal('rmb_amount', 12, 2);
            $table->decimal('fee_ghs', 12, 2)->default(0);
            $table->decimal('total_payable_ghs', 12, 2);
            $table->decimal('ghs_per_rmb', 12, 4);
            $table->string('fee_mode', 12);
            $table->decimal('fee_value', 12, 2);
            $table->foreignId('rate_id')->nullable()->constrained('china_transfer_rates')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('china_transfer_payment_methods')->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->boolean('needs_approval')->default(false);
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->decimal('rmb_sent_amount', 12, 2)->nullable();
            $table->string('rmb_transfer_ref')->nullable();
            $table->timestamp('rmb_sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('china_transfer_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('china_transfer_id')->constrained('china_transfers')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('china_transfer_form_fields')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('china_transfer_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('china_transfer_id')->constrained('china_transfers')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('china_transfer_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('china_transfer_id')->constrained('china_transfers')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('china_transfer_admin_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('china_transfer_id')->constrained('china_transfers')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();
        });

        DB::table('china_transfer_settings')->insert([
            'enabled' => false,
            'channel' => 'alipay',
            'instructions' => 'Send the exact GHS amount to the CityShop payment account, then submit your Alipay details and payment proof. Admin sends RMB to your Alipay after verification.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $now = now();
        $fields = [
            ['recipient', 'image', 'alipay_qr', 'Alipay QR Code', null, "Upload recipient's Alipay QR code", 1, 10],
            ['recipient', 'text', 'alipay_id', 'Alipay Account', "Recipient's Alipay ID", null, 0, 20],
            ['recipient', 'text', 'recipient_name', 'Recipient Name', 'Name of recipient', null, 0, 30],
            ['recipient', 'textarea', 'notes', 'Notes', 'Any additional information', null, 0, 40],
            ['recipient', 'phone', 'recipient_phone', 'Recipient phone', 'Chinese or Ghana number', null, 0, 50],
            ['recipient', 'textarea', 'recipient_address', 'Recipient address', 'Optional', null, 0, 60],
            ['payment', 'text', 'payment_reference', 'Payment reference', 'MoMo or bank reference', 'The exact reference from your GHS payment', 1, 10],
            ['payment', 'image', 'payment_screenshot', 'Payment screenshot', null, 'Screenshot of the GHS payment you sent', 1, 20],
        ];

        foreach ($fields as $field) {
            $name = $field[2];
            DB::table('china_transfer_form_fields')->insert([
                'group' => $field[0],
                'type' => $field[1],
                'name' => $name,
                'label' => $field[3],
                'placeholder' => $field[4],
                'help_text' => $field[5],
                'required' => $field[6],
                'file_types' => in_array($field[1], ['image', 'document', 'files'], true)
                    ? json_encode($field[1] === 'image' ? ['jpg', 'jpeg', 'png', 'webp'] : ['pdf', 'jpg', 'jpeg', 'png'])
                    : null,
                'max_size_kb' => in_array($field[1], ['image', 'document', 'files'], true) ? 5120 : null,
                'sort_order' => $field[7],
                'active' => ! in_array($name, ['recipient_phone', 'recipient_address'], true),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('china_transfer_admin_notes');
        Schema::dropIfExists('china_transfer_status_history');
        Schema::dropIfExists('china_transfer_proofs');
        Schema::dropIfExists('china_transfer_field_values');
        Schema::dropIfExists('china_transfers');
        Schema::dropIfExists('china_transfer_form_fields');
        Schema::dropIfExists('china_transfer_payment_methods');
        Schema::dropIfExists('china_transfer_rates');
        Schema::dropIfExists('china_transfer_settings');
    }
};
