<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_plan_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_plan_id')->constrained()->cascadeOnDelete();
            $table->string('area');
            $table->text('identified_need');
            $table->text('risk')->nullable();
            $table->text('goal');
            $table->text('intervention');
            $table->string('frequency')->nullable();
            $table->foreignId('responsible_staff_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date')->nullable();
            $table->date('review_date')->nullable();
            $table->string('status')->default('ongoing');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_sections');
    }
};
