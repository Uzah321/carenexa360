<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_users', function (Blueprint $table) {
            $table->string('referring_hospital')->nullable()->after('capacity_consent_notes');
            $table->string('hospital_record_number')->nullable()->after('referring_hospital');
            $table->date('discharge_date')->nullable()->after('hospital_record_number');
            $table->text('discharge_summary')->nullable()->after('discharge_date');
        });
    }

    public function down(): void
    {
        Schema::table('service_users', function (Blueprint $table) {
            $table->dropColumn(['referring_hospital', 'hospital_record_number', 'discharge_date', 'discharge_summary']);
        });
    }
};
