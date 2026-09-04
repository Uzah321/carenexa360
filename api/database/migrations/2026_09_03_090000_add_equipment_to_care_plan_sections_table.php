<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_plan_sections', function (Blueprint $table) {
            $table->text('equipment')->nullable()->after('intervention');
        });
    }

    public function down(): void
    {
        Schema::table('care_plan_sections', function (Blueprint $table) {
            $table->dropColumn('equipment');
        });
    }
};
