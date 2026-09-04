<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();

            $table->date('shift_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('shift_type')->default('day');
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
