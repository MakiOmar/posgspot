<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs primary-key columns that lost their AUTO_INCREMENT attribute
 * (observed after a SQL import on some environments). Without it, inserting
 * new rows collides on id=0, breaking product/variation creation entirely.
 *
 * Idempotent: only alters a table when its id column is not already
 * auto-incrementing, so it is safe to run on healthy databases.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'variation_templates',
        'variation_value_templates',
        'variations',
        'variation_location_details',
    ];

    public function up(): void
    {
        $database = DB::getDatabaseName();

        foreach ($this->tables as $table) {
            if (! $this->hasTable($table) || $this->isAutoIncrement($database, $table)) {
                continue;
            }

            // All target id columns are unsigned integer primary keys.
            DB::statement("ALTER TABLE `{$table}` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
        }
    }

    /**
     * Non-destructive: removing AUTO_INCREMENT again would re-break inserts.
     */
    public function down(): void
    {
    }

    private function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function isAutoIncrement(string $database, string $table): bool
    {
        $row = DB::selectOne(
            'SELECT EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$database, $table, 'id']
        );

        return $row !== null && str_contains((string) $row->EXTRA, 'auto_increment');
    }
};
