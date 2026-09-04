<?php

namespace App\Modules\Organization\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'country' => $this->country,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'plan' => $this->plan,
            'status' => $this->status,
            'settings' => $this->settings ?? [],
            'created_at' => $this->created_at,
        ];
    }
}
