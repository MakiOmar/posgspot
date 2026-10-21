<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff-only "Done" marker on sales orders (no business logic depends on it).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('transactions', 'is_done')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_done')->default(false);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('transactions', 'is_done')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropColumn('is_done');
            });
        }
    }
};
