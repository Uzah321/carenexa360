<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carer_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('visit_date');
            $table->time('start_time');
            $table->time('end_time');

            $table->jsonb('care_tasks')->nullable();
            $table->boolean('medication_tasks')->default(false);
            $table->jsonb('required_skills')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();

            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->decimal('check_in_accuracy', 8, 2)->nullable();
            $table->timestamp('check_in_at')->nullable();

            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->decimal('check_out_accuracy', 8, 2)->nullable();
            $table->timestamp('check_out_at')->nullable();

            $table->text('override_reason')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['carer_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
