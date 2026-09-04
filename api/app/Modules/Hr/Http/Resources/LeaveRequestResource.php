<?php

namespace App\Modules\Hr\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'type' => $this->type,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'status' => $this->status,
            'reason' => $this->reason,
            'approved_by' => $this->approved_by,
            'approved_by_name' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy?->name),
            'approved_at' => $this->approved_at,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
