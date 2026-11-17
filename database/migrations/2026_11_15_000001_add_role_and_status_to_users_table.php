<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 1 — User & Access Management.
 * Adds RBAC (role) and account state (status) to users, only when they are missing
 * (the shared Aiven users table already has both).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['OWNER', 'CASHIER'])->default('CASHIER')->after('password');
            });
        }

        if (! Schema::hasColumn('users', 'status')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE')->after('role');
            });
        }
    }

    public function down(): void
    {
        // Deliberately empty: role and status belong to the shared users table.
    }
};
