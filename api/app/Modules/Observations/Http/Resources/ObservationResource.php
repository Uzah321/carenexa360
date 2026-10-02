<?php

namespace App\Modules\Observations\Http\Resources;

use App\Modules\Observations\Support\News2;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ObservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_user_id' => $this->service_user_id,
            'visit_id' => $this->visit_id,
            'type' => $this->type,
            'value' => $this->value,
            'unit' => $this->unit,
            'recorded_by' => $this->recorded_by,
            'recorded_by_name' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
            'recorded_at' => $this->recorded_at,
            'notes' => $this->notes,
            'archived_at' => $this->archived_at,
            // Computed on read so readings recorded before NEWS2 scoring existed are scored too.
            'news2' => is_array($this->value) ? News2::assess($this->type, $this->value) : null,
            'alerts' => ClinicalAlertResource::collection($this->whenLoaded('alerts')),
            'created_at' => $this->created_at,
        ];
    }
}
