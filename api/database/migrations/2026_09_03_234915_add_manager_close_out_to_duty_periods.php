<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Checking in for work is mandatory, so a carer who forgets to check out at
     * the end of their shift would otherwise stay "on duty" indefinitely. A
     * manager can close the period for them — but never silently: the reason and
     * who closed it are recorded, so the shift record still says what happened.
     */
    public function up(): void
    {
        Schema::table('duty_periods', function (Blueprint $table) {
            $table->text('close_reason')->nullable()->after('end_accuracy');
            $table->foreignId('closed_by')->nullable()->after('close_reason')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('duty_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn('close_reason');
        });
    }
};
