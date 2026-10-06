<?php

namespace App\Modules\Quality\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpotCheckResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'staff_user_id' => $this->staff_user_id,
            'staff_name' => $this->whenLoaded('staff', fn () => $this->staff?->name),
            'checked_by' => $this->checked_by,
            'checked_by_name' => $this->whenLoaded('checkedBy', fn () => $this->checkedBy?->name),
            'service_user_id' => $this->service_user_id,
            'service_user_name' => $this->whenLoaded(
                'serviceUser',
                fn () => $this->serviceUser ? trim("{$this->serviceUser->first_name} {$this->serviceUser->last_name}") : null
            ),
            'visit_id' => $this->visit_id,
            'check_date' => $this->check_date?->toDateString(),
            'results' => $this->results ?? [],
            'outcome' => $this->outcome,
            'notes' => $this->notes,
            'actions_required' => $this->actions_required,
            'follow_up_date' => $this->follow_up_date?->toDateString(),
            'created_at' => $this->created_at,
        ];
    }
}
