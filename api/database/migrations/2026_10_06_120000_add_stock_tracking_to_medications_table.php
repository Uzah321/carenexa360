<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional stock tracking: null stock_on_hand means "not tracked".
        // Giving a dose takes units_per_dose off the count.
        Schema::table('medications', function (Blueprint $table) {
            $table->decimal('stock_on_hand', 10, 2)->nullable()->after('is_controlled_drug');
            $table->decimal('reorder_level', 10, 2)->nullable()->after('stock_on_hand');
            $table->decimal('units_per_dose', 8, 2)->default(1)->after('reorder_level');
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->dropColumn(['stock_on_hand', 'reorder_level', 'units_per_dose']);
        });
    }
};
