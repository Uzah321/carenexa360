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
        'hazard',
        'persons_at_risk',
        'harm_description',
        'likelihood',
        'severity',
        'existing_controls',
        'further_actions',
        'residual_likelihood',
        'residual_severity',
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

    public function carePlan(): BelongsTo
    {
        return $this->belongsTo(CarePlan::class);
    }

    public function actionOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'action_owner_id');
    }
}
