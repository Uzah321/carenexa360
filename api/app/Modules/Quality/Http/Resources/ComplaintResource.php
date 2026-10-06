<?php

namespace App\Modules\Quality\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplaintResource extends JsonResource
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
            'received_date' => $this->received_date?->toDateString(),
            'complainant_name' => $this->complainant_name,
            'complainant_relationship' => $this->complainant_relationship,
            'channel' => $this->channel,
            'category' => $this->category,
            'severity' => $this->severity,
            'description' => $this->description,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'assigned_to_name' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo?->name),
            'acknowledged_date' => $this->acknowledged_date?->toDateString(),
            'response_due_date' => $this->response_due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'outcome' => $this->outcome,
            'findings' => $this->findings,
            'actions_taken' => $this->actions_taken,
            'resolved_date' => $this->resolved_date?->toDateString(),
            'created_at' => $this->created_at,
        ];
    }
}
