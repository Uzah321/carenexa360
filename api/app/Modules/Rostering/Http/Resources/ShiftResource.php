<?php

namespace App\Modules\Rostering\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'branch_id' => $this->branch_id,
            'shift_date' => $this->shift_date?->toDateString(),
            // Uncast time columns, so the DB driver's raw "H:i:s" would fail
            // UpdateShiftRequest's strict date_format:H:i if round-tripped
            // unchanged — see the identical fix in VisitResource.
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'shift_type' => $this->shift_type,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
