<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sell_rmb_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->text('instructions')->nullable();
            $table->text('receive_instructions')->nullable();
            $table->timestamps();
        });

        Schema::create('sell_rmb_rates', function (Blueprint $table) {
            $table->id();
            $table->decimal('usd_per_rmb', 12, 6);
            $table->decimal('ghs_per_usd', 12, 4);
            $table->string('fee_mode', 12)->default('flat');
            $table->decimal('fee_value', 12, 2)->default(0);
            $table->decimal('min_rmb', 12, 2)->default(100);
            $table->decimal('max_rmb', 12, 2)->default(50000);
            $table->decimal('daily_max_rmb', 12, 2)->nullable();
            $table->decimal('monthly_max_rmb', 12, 2)->nullable();
            $table->unsignedInteger('max_per_day')->nullable();
            $table->decimal('approval_above_rmb', 12, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sell_rmb_receive_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('alipay');
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('network')->nullable();
            $table->text('instructions')->nullable();
            $table->string('qr_path')->nullable();
            $table->boolean('proof_required')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('sell_rmb_form_fields', function (Blueprint $table) {
            $table->id();
            $table->string('group', 20)->default('payment');
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

        Schema::create('sell_rmb_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32);
            $table->decimal('rmb_amount', 12, 2);
            $table->decimal('usd_per_rmb', 12, 6);
            $table->decimal('ghs_per_usd', 12, 4);
            $table->string('fee_mode', 12);
            $table->decimal('fee_value', 12, 2);
            $table->decimal('fee_usd', 12, 2)->default(0);
            $table->decimal('usd_payout', 12, 2);
            $table->decimal('ghs_payout', 12, 2);
            $table->string('payout_currency', 8);
            $table->foreignId('rate_id')->nullable()->constrained('sell_rmb_rates')->nullOnDelete();
            $table->foreignId('receive_method_id')->nullable()->constrained('sell_rmb_receive_methods')->nullOnDelete();
            $table->string('payment_reference')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->boolean('needs_approval')->default(false);
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('rmb_received_at')->nullable();
            $table->timestamp('payout_processing_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->decimal('payout_amount', 12, 2)->nullable();
            $table->string('payout_ref')->nullable();
            $table->timestamp('payout_paid_at')->nullable();
            $table->string('payout_channel')->nullable();
            $table->timestamps();
        });

        Schema::create('sell_rmb_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sell_rmb_transfer_id')->constrained('sell_rmb_transfers')->cascadeOnDelete();
            $table->foreignId('field_id')->constrained('sell_rmb_form_fields')->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('sell_rmb_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sell_rmb_transfer_id')->constrained('sell_rmb_transfers')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sell_rmb_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sell_rmb_transfer_id')->constrained('sell_rmb_transfers')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sell_rmb_admin_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sell_rmb_transfer_id')->constrained('sell_rmb_transfers')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();
        });

        DB::table('sell_rmb_settings')->insert([
            'enabled' => false,
            'instructions' => 'Sell your RMB to CityShop. Send RMB to our Alipay/WeChat account, submit proof, and receive USD or GHS after verification.',
            'receive_instructions' => 'Send the exact RMB amount to the CityShop receive account shown below, then upload your payment screenshot.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $now = now();
        $fields = [
            ['payment', 'text', 'rmb_sender_name', 'Sender name', 'Name on Alipay/WeChat', 'Name shown on the account that sent RMB', 1, 10],
            ['payment', 'text', 'alipay_or_wechat_account', 'Alipay / WeChat account', 'Account ID or phone', 'The account you used to send RMB', 1, 20],
            ['payment', 'text', 'payment_reference', 'Payment reference', 'Transfer / order number', 'Reference from your RMB payment', 1, 30],
            ['payment', 'image', 'payment_screenshot', 'Payment screenshot', null, 'Screenshot proving you sent the RMB', 1, 40],
            ['payout', 'text', 'payout_name', 'Payout name', 'Name on MoMo or bank', 'Who should receive the USD/GHS payout', 1, 10],
            ['payout', 'phone', 'payout_mobile', 'Payout mobile', 'MoMo or bank mobile', 'Mobile money or contact number for payout', 1, 20],
            ['payout', 'text', 'payout_bank_name', 'Bank name', 'Optional bank name', null, 0, 30],
            ['payout', 'text', 'payout_account_number', 'Account number', 'MoMo or bank account', 'Where we should send your payout', 1, 40],
        ];

        foreach ($fields as $field) {
            DB::table('sell_rmb_form_fields')->insert([
                'group' => $field[0],
                'type' => $field[1],
                'name' => $field[2],
                'label' => $field[3],
                'placeholder' => $field[4],
                'help_text' => $field[5],
                'required' => $field[6],
                'file_types' => in_array($field[1], ['image', 'document', 'files'], true)
                    ? json_encode($field[1] === 'image' ? ['jpg', 'jpeg', 'png', 'webp'] : ['pdf', 'jpg', 'jpeg', 'png'])
                    : null,
                'max_size_kb' => in_array($field[1], ['image', 'document', 'files'], true) ? 5120 : null,
                'sort_order' => $field[7],
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sell_rmb_admin_notes');
        Schema::dropIfExists('sell_rmb_status_history');
        Schema::dropIfExists('sell_rmb_proofs');
        Schema::dropIfExists('sell_rmb_field_values');
        Schema::dropIfExists('sell_rmb_transfers');
        Schema::dropIfExists('sell_rmb_form_fields');
        Schema::dropIfExists('sell_rmb_receive_methods');
        Schema::dropIfExists('sell_rmb_rates');
        Schema::dropIfExists('sell_rmb_settings');
    }
};
