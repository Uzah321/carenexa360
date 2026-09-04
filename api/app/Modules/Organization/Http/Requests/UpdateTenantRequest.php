<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        if ($this->user()->isPlatformAdmin()) {
            return true;
        }

        return $this->user()->tenant_id === $tenant->id
            && $this->user()->hasAnyRole(['Organization Owner', 'Organization Admin']);
    }

    public function rules(): array
    {
        return [
            // Deliberately excludes slug/plan/status — those are
            // identifier/billing concerns a tenant shouldn't self-serve;
            // only a platform admin changes them (via a future dedicated
            // action, not this general-purpose update).
            'name' => ['sometimes', 'string', 'max:255'],
            'country' => ['sometimes', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'max:255'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'settings' => ['sometimes', 'array'],
            'settings.geofence_radius_meters' => ['sometimes', 'integer', 'min:10', 'max:2000'],
            'settings.training_expiry_warning_days' => ['sometimes', 'integer', 'min:1', 'max:180'],
        ];
    }
}
