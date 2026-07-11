<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_shipping_zones', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index();
            $table->string('name');
            $table->unsignedInteger('priority')->default(10)->index();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_catch_all')->default(false);
            $table->timestamps();

            $table->index(['business_id', 'priority']);
        });

        Schema::create('storefront_shipping_zone_locations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('zone_id')->index();
            $table->string('type', 32); // country | state
            $table->string('code', 64);
            $table->timestamps();

            $table->index(['zone_id', 'type', 'code']);
            $table->foreign('zone_id')
                ->references('id')
                ->on('storefront_shipping_zones')
                ->onDelete('cascade');
        });

        Schema::create('storefront_shipping_methods', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('zone_id')->index();
            $table->string('type', 32); // flat_rate | free_shipping | local_pickup
            $table->string('title');
            $table->json('title_i18n')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->foreign('zone_id')
                ->references('id')
                ->on('storefront_shipping_zones')
                ->onDelete('cascade');
        });

        Schema::create('storefront_shipping_classes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'shipping_class_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('shipping_class_id')->nullable()->after('brand_id')->index();
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('transactions', 'storefront_shipping_meta')) {
                    $table->json('storefront_shipping_meta')->nullable()->after('storefront_payment_meta');
                }
                if (! Schema::hasColumn('transactions', 'shipping_tracking_number')) {
                    $table->string('shipping_tracking_number')->nullable()->after('shipping_details');
                }
                if (! Schema::hasColumn('transactions', 'shipping_carrier')) {
                    $table->string('shipping_carrier')->nullable()->after('shipping_tracking_number');
                }
                if (! Schema::hasColumn('transactions', 'shipping_tracking_url')) {
                    $table->string('shipping_tracking_url')->nullable()->after('shipping_carrier');
                }
            });
        }

        Schema::create('storefront_shipments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('business_id')->index();
            $table->unsignedInteger('transaction_id')->index();
            $table->string('carrier', 64);
            $table->string('external_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('label_url')->nullable();
            $table->string('status', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'carrier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_shipments');

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                foreach (['storefront_shipping_meta', 'shipping_tracking_number', 'shipping_carrier', 'shipping_tracking_url'] as $col) {
                    if (Schema::hasColumn('transactions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'shipping_class_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('shipping_class_id');
            });
        }

        Schema::dropIfExists('storefront_shipping_classes');
        Schema::dropIfExists('storefront_shipping_methods');
        Schema::dropIfExists('storefront_shipping_zone_locations');
        Schema::dropIfExists('storefront_shipping_zones');
    }
};
