<?php

namespace App\Modules\Training\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrainingCourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'validity_period_months' => $this->validity_period_months,
            'is_mandatory' => $this->is_mandatory,
            'created_at' => $this->created_at,
        ];
    }
}
