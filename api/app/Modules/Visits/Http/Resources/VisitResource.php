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
            // What a carer has to know before walking in — shown on My Day
            // and the visit page.
            'client_alerts' => $this->whenLoaded('serviceUser', fn () => $this->serviceUser ? [
                'allergies' => $this->clientAllergies(),
                'no_known_allergies' => (bool) $this->serviceUser->no_known_allergies,
                'resuscitation_status' => $this->serviceUser->resuscitation_status,
                'resuscitation_notes' => $this->serviceUser->resuscitation_notes,
            ] : null),
            'carer_id' => $this->carer_id,
            'carer_name' => $this->whenLoaded('carer', fn () => $this->carer?->name),
            'visit_date' => $this->visit_date?->toDateString(),
            // The start_time/end_time columns aren't cast, so the DB driver
            // hands back whatever it stores (often "H:i:s"). Trim to "H:i" so
            // this always matches what UpdateVisitRequest requires — a value
            // round-tripped through the edit form without being touched must
            // still pass validation on save.
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
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

    /**
     * With severity and reaction where the controller eager-loaded the
     * client's current allergies; otherwise just the names from their
     * summary list, so this never costs a query per visit.
     */
    private function clientAllergies(): array
    {
        $serviceUser = $this->serviceUser;

        if ($serviceUser->relationLoaded('currentAllergies')) {
            return $serviceUser->currentAllergies
                ->map(fn ($allergy) => ['name' => $allergy->name, 'severity' => $allergy->severity, 'reaction' => $allergy->reaction])
                ->values()
                ->all();
        }

        return array_map(fn ($name) => ['name' => $name, 'severity' => null, 'reaction' => null], $serviceUser->allergies ?? []);
    }
}
