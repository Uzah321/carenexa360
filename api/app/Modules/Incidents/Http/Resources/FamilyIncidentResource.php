<?php

namespace App\Modules\Incidents\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A family-safe view of an Incident — deliberately excludes
 * investigation_notes/corrective_actions/assigned_to/reviewed_by, which are
 * internal staff-only content, unlike the full IncidentResource.
 */
class FamilyIncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'severity' => $this->severity,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
