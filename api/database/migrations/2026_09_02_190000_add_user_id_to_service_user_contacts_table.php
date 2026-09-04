<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_user_contacts', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('service_user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_user_contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
