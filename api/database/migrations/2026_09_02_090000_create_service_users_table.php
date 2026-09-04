<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('care_manager_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('first_name');
            $table->string('last_name');
            $table->string('preferred_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('language')->nullable();

            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();

            $table->string('funding_source')->nullable();
            $table->string('status')->default('active');

            $table->jsonb('allergies')->nullable();
            $table->jsonb('diagnoses')->nullable();
            $table->jsonb('medical_conditions')->nullable();
            $table->jsonb('disabilities')->nullable();

            $table->text('mobility_notes')->nullable();
            $table->text('communication_needs')->nullable();
            $table->text('dietary_needs')->nullable();
            $table->text('cultural_preferences')->nullable();
            $table->text('religious_requirements')->nullable();
            $table->text('behavioural_considerations')->nullable();
            $table->text('preferred_routines')->nullable();
            $table->text('capacity_consent_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_users');
    }
};
