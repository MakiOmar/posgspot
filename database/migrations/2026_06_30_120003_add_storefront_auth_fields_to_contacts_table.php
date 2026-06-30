<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (! Schema::hasColumn('contacts', 'password')) {
                $table->string('password')->nullable()->after('email');
            }
            if (! Schema::hasColumn('contacts', 'remember_token')) {
                $table->rememberToken();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            if (Schema::hasColumn('contacts', 'password')) {
                $table->dropColumn('password');
            }
            if (Schema::hasColumn('contacts', 'remember_token')) {
                $table->dropColumn('remember_token');
            }
        });
    }
};
