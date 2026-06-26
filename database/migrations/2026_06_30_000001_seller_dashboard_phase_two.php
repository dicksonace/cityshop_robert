<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('cart_adds')->default(0)->after('views');
            $table->unsignedInteger('wishlist_adds')->default(0)->after('cart_adds');
            $table->unsignedInteger('purchase_count')->default(0)->after('wishlist_adds');
            $table->string('meta_title')->nullable()->after('description');
            $table->string('meta_description', 500)->nullable()->after('meta_title');
            $table->string('meta_keywords')->nullable()->after('meta_description');
            $table->decimal('wholesale_price', 12, 2)->nullable()->after('discount_price');
            $table->unsignedInteger('minimum_order_quantity')->default(1)->after('wholesale_price');
            $table->boolean('is_negotiable')->default(false)->after('minimum_order_quantity');
            $table->boolean('cash_on_delivery')->default(false)->after('delivery_days');
            $table->boolean('pickup_available')->default(false)->after('cash_on_delivery');
            $table->boolean('ships_nationwide')->default(true)->after('pickup_available');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->text('seller_reply')->nullable()->after('comment');
            $table->timestamp('seller_replied_at')->nullable()->after('seller_reply');
        });

        Schema::create('seller_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('code');
            $table->string('type')->default('percentage');
            $table->decimal('value', 12, 2);
            $table->decimal('min_order_amount', 12, 2)->default(0);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['seller_id', 'code']);
        });

        Schema::create('seller_coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('checkouts', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0)->after('commission_amount');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount_amount', 12, 2)->default(0)->after('commission_amount');
            $table->foreignId('seller_coupon_id')->nullable()->after('discount_amount')->constrained('seller_coupons')->nullOnDelete();
        });

        Schema::create('product_stat_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('cart_adds')->default(0);
            $table->unsignedInteger('purchases')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stat_daily');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_coupon_id');
            $table->dropColumn('discount_amount');
        });

        Schema::table('checkouts', function (Blueprint $table) {
            $table->dropColumn('discount_amount');
        });

        Schema::dropIfExists('seller_coupon_usages');
        Schema::dropIfExists('seller_coupons');

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['seller_reply', 'seller_replied_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'cart_adds', 'wishlist_adds', 'purchase_count',
                'meta_title', 'meta_description', 'meta_keywords',
                'wholesale_price', 'minimum_order_quantity', 'is_negotiable',
                'cash_on_delivery', 'pickup_available', 'ships_nationwide',
            ]);
        });
    }
};
