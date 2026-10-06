<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Models\Tenant;
use App\Modules\Organization\Support\TenantSettings;
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
            // Used for every "today" and local-time calculation — an
            // unrecognised zone would break them all, so it must be a real one.
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'locale' => ['sometimes', 'string', 'regex:/^[a-z]{2,3}(-[A-Z]{2})?$/'],
            ...TenantSettings::rules(),
        ];
    }
}
