<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->decimal('start_lat', 10, 7);
            $table->decimal('start_lng', 10, 7);
            $table->decimal('start_accuracy', 8, 2)->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->decimal('end_lat', 10, 7)->nullable();
            $table->decimal('end_lng', 10, 7)->nullable();
            $table->decimal('end_accuracy', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_periods');
    }
};
