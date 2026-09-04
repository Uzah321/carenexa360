<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safeguarding_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('victim_name')->nullable();
            $table->text('alleged_perpetrator')->nullable();
            $table->string('concern_type');
            $table->boolean('immediate_risk')->default(false);
            $table->text('external_agencies_notified')->nullable();
            $table->text('investigation_notes')->nullable();
            $table->text('actions_taken')->nullable();
            $table->text('outcome')->nullable();
            $table->string('status')->default('reported');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('confidential_notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safeguarding_cases');
    }
};
