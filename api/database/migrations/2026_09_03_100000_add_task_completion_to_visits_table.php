<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->jsonb('completed_care_tasks')->default('[]')->after('care_tasks');
            $table->boolean('medication_tasks_completed')->default(false)->after('medication_tasks');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['completed_care_tasks', 'medication_tasks_completed']);
        });
    }
};
