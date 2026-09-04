<?php

namespace App\Modules\Training\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->name),
            'training_course_id' => $this->training_course_id,
            'training_course_name' => $this->whenLoaded('trainingCourse', fn () => $this->trainingCourse?->name),
            'completed_date' => $this->completed_date?->toDateString(),
            'expiry_date' => $this->expiry_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'recorded_by' => $this->recorded_by,
            'recorded_by_name' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
            'created_at' => $this->created_at,
        ];
    }
}
