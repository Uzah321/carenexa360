<?php

namespace App\Modules\CarePlanning\Models;

use App\Models\User;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarePlanRiskAssessment extends Model
{
    use BelongsToTenant, HasAuditLog;

    public const TYPES = ['general', 'medication'];

    /** Category of risk — mirrored in web/src/modules/care-planning/risk.ts. */
    public const RISK_TYPES = [
        'falls',
        'moving_and_handling',
        'pressure_ulcers',
        'choking',
        'nutrition_and_hydration',
        'medication',
        'infection_control',
        'environment',
        'fire',
        'self_neglect',
        'behaviour_that_challenges',
        'wandering',
        'self_harm',
        'safeguarding_and_abuse',
        'financial',
        'lone_working',
        'equipment',
        'other',
    ];

    public const PERSONS_AT_RISK = [
        'service_user',
        'care_staff',
        'family_members',
        'other_household_members',
        'visitors',
        'members_of_public',
    ];

    public const MEDICATION_SUPPORT_LEVELS = ['self_administers', 'prompt', 'assist', 'administer'];

    public const MEDICATION_DETAIL_KEYS = [
        'medication_name',
        'dose_route_frequency',
        'support_level',
        'capacity_and_consent',
        'storage',
        'controlled_drug',
        'prn',
        'prn_protocol',
        'side_effects_to_monitor',
        'known_allergies',
        'ordering_and_collection',
        'disposal',
        'error_response',
    ];

    protected $fillable = [
        'tenant_id',
        'care_plan_id',
        'type',
        'area',
        'risk_type',
        'hazard',
        'details',
        'triggers',
        'persons_at_risk',
        'harm_description',
        'likelihood',
        'severity',
        'existing_controls',
        'further_actions',
        'residual_likelihood',
        'residual_severity',
        'target_likelihood',
        'target_severity',
        'contingency_plan_required',
        'contingency_plan',
        'action_owner_id',
        'action_due_date',
        'review_date',
        'medication_details',
    ];

    protected function casts(): array
    {
        return [
            'persons_at_risk' => 'array',
            'medication_details' => 'array',
            'likelihood' => 'integer',
            'severity' => 'integer',
            'residual_likelihood' => 'integer',
            'residual_severity' => 'integer',
            'target_likelihood' => 'integer',
            'target_severity' => 'integer',
            'contingency_plan_required' => 'boolean',
            'action_due_date' => 'date',
            'review_date' => 'date',
        ];
    }

    public function riskScore(): ?int
    {
        return $this->likelihood && $this->severity ? $this->likelihood * $this->severity : null;
    }

    public function residualRiskScore(): ?int
    {
        return $this->residual_likelihood && $this->residual_severity
            ? $this->residual_likelihood * $this->residual_severity
            : null;
    }

    public function targetRiskScore(): ?int
    {
        return $this->target_likelihood && $this->target_severity
            ? $this->target_likelihood * $this->target_severity
            : null;
    }

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function actionOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_owner_id');
    }
}
