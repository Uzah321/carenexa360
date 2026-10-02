<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NEWS2 full-set alerts list every abnormal parameter plus the
        // escalation guidance, which overruns a 255-char string.
        Schema::table('clinical_alerts', function (Blueprint $table) {
            $table->text('message')->change();
        });
    }

    public function down(): void
    {
        Schema::table('clinical_alerts', function (Blueprint $table) {
            $table->string('message')->change();
        });
    }
};
