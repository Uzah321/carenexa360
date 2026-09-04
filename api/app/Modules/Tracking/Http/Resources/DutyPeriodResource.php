<?php

namespace App\Modules\Tracking\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DutyPeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'carer_name' => $this->whenLoaded('carer', fn () => $this->carer?->name),
            'started_at' => $this->started_at,
            'ended_at' => $this->ended_at,
            'is_active' => is_null($this->ended_at),
            'close_reason' => $this->close_reason,
            'closed_by_name' => $this->whenLoaded('closedBy', fn () => $this->closedBy?->name),
        ];
    }
}
