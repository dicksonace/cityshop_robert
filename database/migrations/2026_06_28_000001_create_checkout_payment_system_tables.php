<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->boolean('accept_marketplace_payments')->default(true)->after('total_sales');
            $table->boolean('accept_direct_payments')->default(false)->after('accept_marketplace_payments');
        });

        Schema::create('seller_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_profile_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('label')->nullable();
            $table->string('account_name');
            $table->string('account_number')->nullable();
            $table->string('network')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('instructions')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->string('checkout_number')->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('pending');
            $table->string('receiver_name');
            $table->string('receiver_phone');
            $table->string('region');
            $table->string('city');
            $table->string('digital_address')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('checkout_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->after('buyer_id')->constrained('users')->nullOnDelete();
            $table->string('payment_channel')->default('marketplace')->after('payment_method');
            $table->foreignId('seller_payment_method_id')->nullable()->after('payment_channel')->constrained('seller_payment_methods')->nullOnDelete();
            $table->string('direct_payment_reference')->nullable()->after('payment_reference');
            $table->timestamp('direct_payment_confirmed_at')->nullable()->after('direct_payment_reference');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel');
            $table->string('method')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('checkout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->json('line_items');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable();
            $table->timestamp('issued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        $this->backfillCheckouts();
    }

    private function backfillCheckouts(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        DB::table('orders')->orderBy('id')->chunkById(100, function ($orders) {
            foreach ($orders as $order) {
                if ($order->checkout_id) {
                    continue;
                }

                $checkoutId = DB::table('checkouts')->insertGetId([
                    'checkout_number' => 'CHK-LEGACY-'.$order->id,
                    'buyer_id' => $order->buyer_id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'receiver_name' => $order->receiver_name,
                    'receiver_phone' => $order->receiver_phone,
                    'region' => $order->region,
                    'city' => $order->city,
                    'digital_address' => $order->digital_address,
                    'delivery_notes' => $order->delivery_notes,
                    'subtotal' => $order->subtotal,
                    'shipping_cost' => $order->shipping_cost,
                    'commission_amount' => $order->commission_amount,
                    'total' => $order->total,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                ]);

                $sellerId = DB::table('order_items')->where('order_id', $order->id)->value('seller_id');

                DB::table('orders')->where('id', $order->id)->update([
                    'checkout_id' => $checkoutId,
                    'seller_id' => $sellerId,
                    'payment_channel' => 'marketplace',
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_payment_method_id');
            $table->dropConstrainedForeignId('checkout_id');
            $table->dropConstrainedForeignId('seller_id');
            $table->dropColumn(['payment_channel', 'direct_payment_reference', 'direct_payment_confirmed_at']);
        });
        Schema::dropIfExists('checkouts');
        Schema::dropIfExists('seller_payment_methods');
        Schema::table('seller_profiles', function (Blueprint $table) {
            $table->dropColumn(['accept_marketplace_payments', 'accept_direct_payments']);
        });
    }
};
