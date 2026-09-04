<?php

namespace App\Modules\Incidents\Http\Requests;

use App\Modules\Incidents\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'service_user_id' => [
                'nullable',
                'integer',
                Rule::exists('service_users', 'id')->where('tenant_id', $tenantId),
            ],
            'type' => ['required', 'string', Rule::in(Incident::TYPES)],
            'severity' => ['required', 'string', Rule::in(Incident::SEVERITIES)],
            'description' => ['required', 'string'],
            'immediate_action' => ['nullable', 'string'],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
