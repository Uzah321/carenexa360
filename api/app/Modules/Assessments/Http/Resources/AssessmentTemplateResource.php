<?php

namespace App\Modules\Assessments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'fields' => $this->fields,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
