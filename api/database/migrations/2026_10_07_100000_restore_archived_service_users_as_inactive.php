<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Service users no longer have an "Archive" (soft delete) — they are either
 * deactivated or permanently deleted. Anyone archived before this would
 * otherwise sit hidden with no way to reach them, so bring them back as
 * inactive, which is what archiving was standing in for.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_users')
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null, 'status' => 'inactive']);
    }

    public function down(): void
    {
        // Not reversible — which rows were archived isn't kept.
    }
};
