<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('business_id');
            $table->string('code', 64);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 32);
            $table->decimal('discount_amount', 22, 4)->default(0);
            $table->decimal('max_discount_amount', 22, 4)->nullable();
            $table->decimal('min_order_subtotal', 22, 4)->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('channel', 16)->default('storefront');
            $table->unsignedInteger('max_uses_total')->nullable();
            $table->unsignedInteger('max_uses_per_customer')->nullable();
            $table->boolean('first_order_only')->default(false);
            $table->boolean('exclude_sale_items')->default(false);
            $table->boolean('stack_with_reward_points')->default(true);
            $table->string('applies_to', 16)->default('all');
            $table->unsignedInteger('times_used')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'code']);
            $table->index(['business_id', 'is_active']);
        });

        Schema::create('coupon_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedInteger('category_id');
            $table->primary(['coupon_id', 'category_id']);
        });

        Schema::create('coupon_products', function (Blueprint $table) {
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedInteger('variation_id');
            $table->primary(['coupon_id', 'variation_id']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('coupon_id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id')->nullable();
            $table->unsignedInteger('transaction_id');
            $table->decimal('discount_amount', 22, 4)->default(0);
            $table->string('channel', 16)->default('storefront');
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->unique(['coupon_id', 'transaction_id']);
            $table->index(['coupon_id', 'contact_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'storefront_coupon_id')) {
                $table->unsignedBigInteger('storefront_coupon_id')->nullable()->after('storefront_payment_meta');
                $table->string('storefront_coupon_code', 64)->nullable()->after('storefront_coupon_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'storefront_coupon_code')) {
                $table->dropColumn(['storefront_coupon_code', 'storefront_coupon_id']);
            }
        });

        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupon_products');
        Schema::dropIfExists('coupon_categories');
        Schema::dropIfExists('coupons');
    }
};
