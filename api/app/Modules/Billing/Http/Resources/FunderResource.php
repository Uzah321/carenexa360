<?php

namespace App\Modules\Billing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FunderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'contact_name' => $this->contact_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'default_hourly_rate' => $this->default_hourly_rate,
            'notes' => $this->notes,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
