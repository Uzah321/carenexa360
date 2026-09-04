<?php

namespace App\Modules\ServiceUsers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceUserContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_user_id' => $this->service_user_id,
            'user_id' => $this->user_id,
            'has_portal_access' => (bool) $this->user_id,
            'type' => $this->type,
            'name' => $this->name,
            'relationship' => $this->relationship,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'notes' => $this->notes,
        ];
    }
}
