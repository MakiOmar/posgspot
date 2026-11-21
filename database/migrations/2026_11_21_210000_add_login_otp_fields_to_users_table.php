<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'login_otp_hash')) {
                $table->string('login_otp_hash', 255)->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'login_otp_expires_at')) {
                $table->timestamp('login_otp_expires_at')->nullable()->after('login_otp_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['login_otp_hash', 'login_otp_expires_at'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
