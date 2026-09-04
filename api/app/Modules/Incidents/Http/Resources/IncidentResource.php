<?php

namespace App\Modules\Incidents\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class IncidentResource extends JsonResource
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
            'type' => $this->type,
            'severity' => $this->severity,
            'description' => $this->description,
            'immediate_action' => $this->immediate_action,
            'status' => $this->status,
            'reported_by' => $this->reported_by,
            'reported_by_name' => $this->whenLoaded('reportedBy', fn () => $this->reportedBy?->name),
            'assigned_to' => $this->assigned_to,
            'assigned_to_name' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo?->name),
            'investigation_notes' => $this->investigation_notes,
            'corrective_actions' => $this->corrective_actions,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at,
            'closed_at' => $this->closed_at,
            'created_at' => $this->created_at,
        ];
    }
}
