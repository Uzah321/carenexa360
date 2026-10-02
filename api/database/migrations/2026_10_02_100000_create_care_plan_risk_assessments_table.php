<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Risk assessments are versioned with their care plan, exactly like
        // care_plan_sections — each new plan version gets its own copies, so
        // an archived version keeps the assessments that applied at the time.
        Schema::create('care_plan_risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('care_plan_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('general');
            $table->string('area')->nullable();
            $table->text('hazard');
            $table->json('persons_at_risk')->nullable();
            $table->text('harm_description')->nullable();
            $table->unsignedTinyInteger('likelihood')->nullable();
            $table->unsignedTinyInteger('severity')->nullable();
            $table->text('existing_controls')->nullable();
            $table->text('further_actions')->nullable();
            $table->unsignedTinyInteger('residual_likelihood')->nullable();
            $table->unsignedTinyInteger('residual_severity')->nullable();
            $table->foreignId('action_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('action_due_date')->nullable();
            $table->date('review_date')->nullable();
            // Only set for type = medication — see CarePlanRiskAssessment::MEDICATION_DETAIL_KEYS.
            $table->json('medication_details')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'care_plan_id']);
        });

        DB::statement('ALTER TABLE care_plan_risk_assessments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE care_plan_risk_assessments FORCE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY tenant_isolation ON care_plan_risk_assessments
            USING (
                current_setting('app.current_tenant_id', true) IS NULL
                OR current_setting('app.current_tenant_id', true) = ''
                OR tenant_id = NULLIF(current_setting('app.current_tenant_id', true), '')::bigint
            )
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('care_plan_risk_assessments');
    }
};
