<?php

namespace App\Modules\Assessments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentResponseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_user_id' => $this->service_user_id,
            'assessment_template_id' => $this->assessment_template_id,
            'template_name' => $this->whenLoaded('template', fn () => $this->template?->name),
            'answers' => $this->answers,
            'completed_by' => $this->completed_by,
            'completed_by_name' => $this->whenLoaded('completedBy', fn () => $this->completedBy?->name),
            'completed_at' => $this->completed_at,
            'status' => $this->status,
        ];
    }
}
