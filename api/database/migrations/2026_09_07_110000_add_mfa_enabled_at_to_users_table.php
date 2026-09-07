<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null = 2FA not active. Set once the user has proven they can
            // generate a valid code (see TwoFactorController::confirm) — a
            // secret alone isn't enough, since generating one and never
            // finishing setup must not lock anyone out.
            $table->timestamp('mfa_enabled_at')->nullable()->after('mfa_secret');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mfa_enabled_at');
        });
    }
};
