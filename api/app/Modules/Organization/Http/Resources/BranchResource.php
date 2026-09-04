<?php

namespace App\Modules\Organization\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'country' => $this->country,
            'region' => $this->region,
            'address' => $this->address,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
