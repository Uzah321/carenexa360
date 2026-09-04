<?php

namespace App\Modules\Medications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_user_id' => $this->service_user_id,
            'name' => $this->name,
            'strength' => $this->strength,
            'form' => $this->form,
            'dose' => $this->dose,
            'route' => $this->route,
            'frequency' => $this->frequency,
            'schedule' => $this->schedule ?? [],
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'prescriber' => $this->prescriber,
            'pharmacy' => $this->pharmacy,
            'instructions' => $this->instructions,
            'is_prn' => $this->is_prn,
            'prn_instructions' => $this->prn_instructions,
            'is_controlled_drug' => $this->is_controlled_drug,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'administrations' => MedicationAdministrationResource::collection($this->whenLoaded('administrations')),
            'created_at' => $this->created_at,
        ];
    }
}
