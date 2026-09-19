<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsm_order_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gsm_order_id')->constrained('gsm_orders')->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['gsm_order_id', 'id']);
        });

        $orders = DB::table('gsm_orders')
            ->whereNotNull('admin_result_note')
            ->where('admin_result_note', '!=', '')
            ->get(['id', 'assigned_admin_id', 'admin_result_note', 'updated_at', 'created_at']);

        foreach ($orders as $order) {
            DB::table('gsm_order_replies')->insert([
                'gsm_order_id' => $order->id,
                'admin_id' => $order->assigned_admin_id,
                'body' => $order->admin_result_note,
                'created_at' => $order->updated_at ?? $order->created_at ?? now(),
                'updated_at' => $order->updated_at ?? $order->created_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gsm_order_replies');
    }
};
