<?php

namespace App\Modules\CarePlanning\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarePlanSectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'area' => $this->area,
            'identified_need' => $this->identified_need,
            'risk' => $this->risk,
            'goal' => $this->goal,
            'intervention' => $this->intervention,
            'equipment' => $this->equipment,
            'frequency' => $this->frequency,
            'responsible_staff_id' => $this->responsible_staff_id,
            'responsible_staff_name' => $this->whenLoaded('responsibleStaff', fn () => $this->responsibleStaff?->name),
            'start_date' => $this->start_date?->toDateString(),
            'review_date' => $this->review_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }
}
