<?php

namespace App\Modules\Compliance\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ComplianceRequirementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'jurisdiction' => $this->jurisdiction,
            'status' => $this->status,
            'expiry_status' => $this->expiry_status,
            'issued_date' => $this->issued_date?->toDateString(),
            'renewal_date' => $this->renewal_date?->toDateString(),
            'reference_number' => $this->reference_number,
            'responsible_user_id' => $this->responsible_user_id,
            'responsible_user_name' => $this->whenLoaded('responsibleUser', fn () => $this->responsibleUser?->name),
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}
