<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Composite index for stock lookups by variation + location (cart/availability).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('variation_location_details')) {
            return;
        }

        if ($this->hasVariationLocationCompositeIndex()) {
            return;
        }

        Schema::table('variation_location_details', function (Blueprint $table) {
            $table->index(['variation_id', 'location_id'], 'vld_variation_location_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('variation_location_details')) {
            return;
        }

        if (! Schema::hasIndex('variation_location_details', 'vld_variation_location_index')) {
            return;
        }

        Schema::table('variation_location_details', function (Blueprint $table) {
            $table->dropIndex('vld_variation_location_index');
        });
    }

    private function hasVariationLocationCompositeIndex(): bool
    {
        if (Schema::hasIndex('variation_location_details', 'vld_variation_location_index')) {
            return true;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql' && $driver !== 'mariadb') {
            return false;
        }

        $rows = DB::select(
            'SHOW INDEX FROM variation_location_details WHERE Key_name != ?',
            ['PRIMARY']
        );

        $byKey = [];
        foreach ($rows as $row) {
            $key = (string) ($row->Key_name ?? '');
            $seq = (int) ($row->Seq_in_index ?? 0);
            $col = (string) ($row->Column_name ?? '');
            if ($key === '' || $seq < 1 || $col === '') {
                continue;
            }
            $byKey[$key][$seq] = $col;
        }

        foreach ($byKey as $cols) {
            ksort($cols);
            $ordered = array_values($cols);
            if ($ordered === ['variation_id', 'location_id'] || $ordered === ['location_id', 'variation_id']) {
                return true;
            }
        }

        return false;
    }
};
