<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            // The medication's schedule slot ("19:30") this record answers for.
            $table->string('scheduled_time', 5)->nullable()->after('status');
            $table->string('not_given_reason')->nullable()->after('scheduled_time');
            $table->boolean('stock_checked')->nullable()->after('not_given_reason');
        });
    }

    public function down(): void
    {
        Schema::table('medication_administrations', function (Blueprint $table) {
            $table->dropColumn(['scheduled_time', 'not_given_reason', 'stock_checked']);
        });
    }
};
