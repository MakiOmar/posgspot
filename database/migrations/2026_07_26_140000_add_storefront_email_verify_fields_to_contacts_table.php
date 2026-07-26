<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (! Schema::hasColumn('contacts', 'email_verify_code_hash')) {
                $table->string('email_verify_code_hash', 255)->nullable()->after('email_verified_at');
            }
            if (! Schema::hasColumn('contacts', 'email_verify_expires_at')) {
                $table->timestamp('email_verify_expires_at')->nullable()->after('email_verify_code_hash');
            }
            if (! Schema::hasColumn('contacts', 'storefront_delete_requested_at')) {
                $table->timestamp('storefront_delete_requested_at')->nullable()->after('email_verify_expires_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            foreach ([
                'email_verified_at',
                'email_verify_code_hash',
                'email_verify_expires_at',
                'storefront_delete_requested_at',
            ] as $column) {
                if (Schema::hasColumn('contacts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
