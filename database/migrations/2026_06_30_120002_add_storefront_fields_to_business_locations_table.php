<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('business_locations', 'sells_online')) {
                $table->boolean('sells_online')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('business_locations', 'enable_pickup')) {
                $table->boolean('enable_pickup')->default(false)->after('sells_online');
            }
            if (! Schema::hasColumn('business_locations', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('enable_pickup');
            }
            if (! Schema::hasColumn('business_locations', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_locations', function (Blueprint $table) {
            $columns = ['sells_online', 'enable_pickup', 'latitude', 'longitude'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('business_locations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
