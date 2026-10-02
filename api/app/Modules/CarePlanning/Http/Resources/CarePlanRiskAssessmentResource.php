<?php

namespace App\Modules\CarePlanning\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarePlanRiskAssessmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'area' => $this->area,
            'hazard' => $this->hazard,
            'persons_at_risk' => $this->persons_at_risk ?? [],
            'harm_description' => $this->harm_description,
            'likelihood' => $this->likelihood,
            'severity' => $this->severity,
            'risk_score' => $this->riskScore(),
            'existing_controls' => $this->existing_controls,
            'further_actions' => $this->further_actions,
            'residual_likelihood' => $this->residual_likelihood,
            'residual_severity' => $this->residual_severity,
            'residual_risk_score' => $this->residualRiskScore(),
            'action_owner_id' => $this->action_owner_id,
            'action_owner_name' => $this->whenLoaded('actionOwner', fn () => $this->actionOwner?->name),
            'action_due_date' => $this->action_due_date?->toDateString(),
            'review_date' => $this->review_date?->toDateString(),
            'medication_details' => $this->medication_details,
        ];
    }
}
