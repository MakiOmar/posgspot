<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Display Egyptian Pound as L.E. in POS and storefront.
     */
    public function up(): void
    {
        DB::table('currencies')
            ->where('code', 'EGP')
            ->update(['symbol' => 'L.E.']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('currencies')
            ->where('code', 'EGP')
            ->update(['symbol' => '£']);
    }
};
