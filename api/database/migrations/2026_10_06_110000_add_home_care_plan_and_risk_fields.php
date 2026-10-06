<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_users', function (Blueprint $table) {
            $table->string('nhs_number', 20)->nullable()->after('date_of_birth');
        });

        // The narrative "Home Care Plan" — About Me, goals, needs and consent
        // per area, summaries — versioned with the plan like its sections.
        // Shape: see App\Modules\CarePlanning\Support\HomeCarePlan.
        Schema::table('care_plans', function (Blueprint $table) {
            $table->json('home_care_plan')->nullable()->after('notes');
        });

        Schema::table('care_plan_risk_assessments', function (Blueprint $table) {
            $table->string('risk_type')->nullable()->after('area');
            $table->text('details')->nullable()->after('hazard');
            $table->text('triggers')->nullable()->after('details');
            // The score the plan is aiming for — distinct from residual, which
            // is where the risk actually sits once controls are in place.
            $table->unsignedTinyInteger('target_likelihood')->nullable()->after('residual_severity');
            $table->unsignedTinyInteger('target_severity')->nullable()->after('target_likelihood');
            $table->boolean('contingency_plan_required')->default(false)->after('target_severity');
            $table->text('contingency_plan')->nullable()->after('contingency_plan_required');
        });
    }

    public function down(): void
    {
        Schema::table('care_plan_risk_assessments', function (Blueprint $table) {
            $table->dropColumn([
                'risk_type',
                'details',
                'triggers',
                'target_likelihood',
                'target_severity',
                'contingency_plan_required',
                'contingency_plan',
            ]);
        });

        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropColumn('home_care_plan');
        });

        Schema::table('service_users', function (Blueprint $table) {
            $table->dropColumn('nhs_number');
        });
    }
};
