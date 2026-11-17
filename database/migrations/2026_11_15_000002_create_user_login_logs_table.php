<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 1 — Security audit trail. One row per successful login.
 * Created only if missing (it already exists on the shared Aiven database).
 * Same columns as the Aiven table, including created_at / updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_login_logs')) {
            return;
        }

        Schema::create('user_login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('login_at')->useCurrent();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'login_at']);
        });
    }

    /** Deliberately empty: the login history is shared, audit data and must never be dropped by a rollback. */
    public function down(): void
    {
        //
    }
};
