<?php

namespace App\Modules\Safeguarding\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SafeguardingCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_user_id' => $this->service_user_id,
            'service_user_name' => $this->whenLoaded(
                'serviceUser',
                fn () => $this->serviceUser ? trim("{$this->serviceUser->first_name} {$this->serviceUser->last_name}") : null
            ),
            'victim_name' => $this->victim_name,
            'alleged_perpetrator' => $this->alleged_perpetrator,
            'concern_type' => $this->concern_type,
            'immediate_risk' => $this->immediate_risk,
            'external_agencies_notified' => $this->external_agencies_notified,
            'investigation_notes' => $this->investigation_notes,
            'actions_taken' => $this->actions_taken,
            'outcome' => $this->outcome,
            'status' => $this->status,
            'reported_by' => $this->reported_by,
            'reported_by_name' => $this->whenLoaded('reportedBy', fn () => $this->reportedBy?->name),
            'confidential_notes' => $this->confidential_notes,
            'created_at' => $this->created_at,
        ];
    }
}
