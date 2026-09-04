<?php

namespace App\Modules\Observations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicalAlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_user_id' => $this->service_user_id,
            'observation_id' => $this->observation_id,
            'message' => $this->message,
            'severity' => $this->severity,
            'acknowledged_at' => $this->acknowledged_at,
            'acknowledged_by' => $this->acknowledged_by,
            'acknowledged_by_name' => $this->whenLoaded('acknowledgedBy', fn () => $this->acknowledgedBy?->name),
            'created_at' => $this->created_at,
        ];
    }
}
