<?php

namespace App\Modules\CarePlanning\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_user_id' => $this->service_user_id,
            'version' => $this->version,
            'status' => $this->status,
            'effective_from' => $this->effective_from?->toDateString(),
            'created_by' => $this->created_by,
            'created_by_name' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'notes' => $this->notes,
            'home_care_plan' => $this->home_care_plan,
            'sections' => CarePlanSectionResource::collection($this->whenLoaded('sections')),
            'risk_assessments' => CarePlanRiskAssessmentResource::collection($this->whenLoaded('riskAssessments')),
            'created_at' => $this->created_at,
        ];
    }
}
