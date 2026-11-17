<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base tables. Staff log in with a USERNAME (there is no email column), which matches
 * the users table that already exists on the shared Aiven database.
 *
 * Every table is created only if it is missing, so running this against the shared
 * Aiven database (where these tables already exist) changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('username')->unique();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }
    }

    /**
     * Deliberately empty: `users` and `sessions` are shared with the rest of the team,
     * so a rollback must never drop them.
     */
    public function down(): void
    {
        //
    }
};
