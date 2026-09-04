<?php

namespace App\Modules\ServiceUsers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'care_manager_id' => $this->care_manager_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'preferred_name' => $this->preferred_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender,
            'language' => $this->language,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            // Eloquent's decimal:7 cast returns a string (to preserve
            // precision) — cast to float here so the frontend gets real
            // numbers to hand straight to Leaflet/Haversine math.
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'funding_source' => $this->funding_source,
            'status' => $this->status,
            'allergies' => $this->allergies ?? [],
            'diagnoses' => $this->diagnoses ?? [],
            'medical_conditions' => $this->medical_conditions ?? [],
            'disabilities' => $this->disabilities ?? [],
            'mobility_notes' => $this->mobility_notes,
            'communication_needs' => $this->communication_needs,
            'dietary_needs' => $this->dietary_needs,
            'cultural_preferences' => $this->cultural_preferences,
            'religious_requirements' => $this->religious_requirements,
            'behavioural_considerations' => $this->behavioural_considerations,
            'preferred_routines' => $this->preferred_routines,
            'capacity_consent_notes' => $this->capacity_consent_notes,
            'carers' => $this->whenLoaded('carers', fn () => $this->carers->map(fn ($carer) => [
                'id' => $carer->id,
                'name' => $carer->name,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
