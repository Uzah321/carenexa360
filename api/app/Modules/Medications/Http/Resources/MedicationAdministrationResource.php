<?php

namespace App\Modules\Medications\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicationAdministrationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medication_id' => $this->medication_id,
            'visit_id' => $this->visit_id,
            'status' => $this->status,
            'administered_at' => $this->administered_at,
            'administered_by' => $this->administered_by,
            'administered_by_name' => $this->whenLoaded('administeredBy', fn () => $this->administeredBy?->name),
            'witness_id' => $this->witness_id,
            'witness_name' => $this->whenLoaded('witness', fn () => $this->witness?->name),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
