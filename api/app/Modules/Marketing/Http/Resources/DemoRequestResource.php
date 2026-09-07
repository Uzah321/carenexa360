<?php

namespace App\Modules\Marketing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DemoRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'organization_name' => $this->organization_name,
            'phone' => $this->phone,
            'message' => $this->message,
            'created_at' => $this->created_at,
        ];
    }
}
