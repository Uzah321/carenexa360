<?php

namespace App\Modules\Visits\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_user_id' => $this->service_user_id,
            'service_user_name' => $this->whenLoaded(
                'serviceUser',
                fn () => trim("{$this->serviceUser?->first_name} {$this->serviceUser?->last_name}")
            ),
            'carer_id' => $this->carer_id,
            'carer_name' => $this->whenLoaded('carer', fn () => $this->carer?->name),
            'visit_date' => $this->visit_date?->toDateString(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'care_tasks' => $this->care_tasks ?? [],
            'completed_care_tasks' => $this->completed_care_tasks ?? [],
            'medication_tasks' => $this->medication_tasks,
            'medication_tasks_completed' => $this->medication_tasks_completed,
            'required_skills' => $this->required_skills ?? [],
            'priority' => $this->priority,
            'status' => $this->status,
            'notes' => $this->notes,
            'check_in_at' => $this->check_in_at,
            'check_in_lat' => $this->check_in_lat,
            'check_in_lng' => $this->check_in_lng,
            'check_out_at' => $this->check_out_at,
            'check_out_lat' => $this->check_out_lat,
            'check_out_lng' => $this->check_out_lng,
            'override_reason' => $this->override_reason,
            'created_at' => $this->created_at,
        ];
    }
}
