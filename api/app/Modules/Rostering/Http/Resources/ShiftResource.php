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
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'shift_type' => $this->shift_type,
            'status' => $this->status,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
