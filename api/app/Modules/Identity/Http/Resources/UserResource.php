<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Organization\Support\TenantSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'mfa_enabled' => $this->hasTwoFactorEnabled(),
            'roles' => $this->getRoleNames(),
            'permissions' => $this->getAllPermissions()->pluck('name'),
            // The organisation's preferences the whole app formats and
            // suggests by: currency, locale, timezone, reference lists,
            // pathway timescales. Platform admins have no tenant.
            'tenant' => $this->tenant ? [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'country' => $this->tenant->country,
                'timezone' => $this->tenant->timezone,
                'currency' => $this->tenant->currency,
                'locale' => $this->tenant->locale,
                'settings' => TenantSettings::effective($this->tenant->settings),
            ] : null,
        ];
    }
}
