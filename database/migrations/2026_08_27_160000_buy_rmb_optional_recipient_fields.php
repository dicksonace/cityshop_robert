<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buy RMB (rmb-wallet style): QR required; Alipay account / name / notes optional.
 * Hide phone + address from the buyer form.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('china_transfer_form_fields')) {
            return;
        }

        $now = now();

        DB::table('china_transfer_form_fields')->where('name', 'alipay_qr')->update([
            'label' => 'Alipay QR Code',
            'placeholder' => null,
            'help_text' => "Upload recipient's Alipay QR code",
            'required' => true,
            'active' => true,
            'sort_order' => 10,
            'updated_at' => $now,
        ]);

        DB::table('china_transfer_form_fields')->where('name', 'alipay_id')->update([
            'label' => 'Alipay Account',
            'placeholder' => "Recipient's Alipay ID",
            'help_text' => null,
            'required' => false,
            'active' => true,
            'sort_order' => 20,
            'updated_at' => $now,
        ]);

        DB::table('china_transfer_form_fields')->where('name', 'recipient_name')->update([
            'label' => 'Recipient Name',
            'placeholder' => 'Name of recipient',
            'help_text' => null,
            'required' => false,
            'active' => true,
            'sort_order' => 30,
            'updated_at' => $now,
        ]);

        DB::table('china_transfer_form_fields')->where('name', 'recipient_phone')->update([
            'required' => false,
            'active' => false,
            'updated_at' => $now,
        ]);

        $notes = DB::table('china_transfer_form_fields')->where('name', 'notes')->first();
        if ($notes) {
            DB::table('china_transfer_form_fields')->where('id', $notes->id)->update([
                'type' => 'textarea',
                'label' => 'Notes',
                'placeholder' => 'Any additional information',
                'help_text' => null,
                'required' => false,
                'active' => true,
                'sort_order' => 40,
                'updated_at' => $now,
            ]);
            DB::table('china_transfer_form_fields')->where('name', 'recipient_address')->update([
                'active' => false,
                'required' => false,
                'updated_at' => $now,
            ]);
        } else {
            $address = DB::table('china_transfer_form_fields')->where('name', 'recipient_address')->first();
            if ($address) {
                DB::table('china_transfer_form_fields')->where('id', $address->id)->update([
                    'name' => 'notes',
                    'type' => 'textarea',
                    'label' => 'Notes',
                    'placeholder' => 'Any additional information',
                    'help_text' => null,
                    'required' => false,
                    'active' => true,
                    'sort_order' => 40,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('china_transfer_form_fields')->insert([
                    'group' => 'recipient',
                    'type' => 'textarea',
                    'name' => 'notes',
                    'label' => 'Notes',
                    'placeholder' => 'Any additional information',
                    'help_text' => null,
                    'required' => false,
                    'options' => null,
                    'file_types' => null,
                    'max_size_kb' => null,
                    'sort_order' => 40,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: leave buyer-friendly optional fields in place.
    }
};
