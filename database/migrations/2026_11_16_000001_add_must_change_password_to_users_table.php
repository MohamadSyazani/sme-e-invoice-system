<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 1 — forces a new account (or a password reset by the Owner) to choose
 * its own password on the next login. Existing accounts default to false (unaffected).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'must_change_password')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('must_change_password');
            });
        }
    }
};
