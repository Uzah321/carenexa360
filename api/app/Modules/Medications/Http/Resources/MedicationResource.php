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
            'stock_on_hand' => $this->stock_on_hand,
            'reorder_level' => $this->reorder_level,
            'units_per_dose' => $this->units_per_dose,
            'days_of_stock_left' => $this->daysOfStockLeft(),
            'needs_reorder' => $this->needsReorder(),
            'status' => $this->status,
            'archived_at' => $this->archived_at,
            'created_by' => $this->created_by,
            'administrations' => MedicationAdministrationResource::collection($this->whenLoaded('administrations')),
            'today_administrations' => MedicationAdministrationResource::collection($this->whenLoaded('todayAdministrations')),
            'created_at' => $this->created_at,
        ];
    }
}
