<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Suspending/reactivating another organization's account is a
        // platform-level concern — deliberately not delegated to a tenant's
        // own Organization Owner/Admin the way general company-detail
        // edits are (see UpdateTenantRequest).
        return $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(Tenant::STATUSES)],
        ];
    }
}
