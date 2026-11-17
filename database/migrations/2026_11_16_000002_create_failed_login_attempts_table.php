<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 1 — security audit of FAILED logins.
 * Kept separate from user_login_logs so the successful-login audit table is unchanged.
 * The password that was typed is never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('failed_login_attempts')) {
            return;
        }

        // A real foreign key needs users.id to be an UNSIGNED bigint. Tables created by hand
        // (e.g. in DBeaver) sometimes use a signed bigint, which MySQL refuses to link,
        // so in that case user_id is a plain indexed column instead.
        $idType = strtolower((string) (collect(Schema::getColumns('users'))->firstWhere('name', 'id')['type'] ?? ''));
        $canLink = $this->isMysql() ? str_contains($idType, 'unsigned') : true;

        Schema::create('failed_login_attempts', function (Blueprint $table) use ($canLink) {
            $table->id();
            // The username that was typed (it may not belong to any account).
            $table->string('username');
            // Filled in only when the username matched a real account.
            if ($canLink) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }
            $table->string('reason', 20); // UNKNOWN_USERNAME | WRONG_PASSWORD | INACTIVE_ACCOUNT
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('attempted_at')->useCurrent();

            $table->index('attempted_at');
            $table->index(['ip_address', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_login_attempts');
    }

    private function isMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};
