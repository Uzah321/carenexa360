<?php

namespace App\Modules\Incidents\Http\Requests;

use App\Modules\Incidents\Models\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'type' => ['sometimes', 'string', Rule::in(Incident::TYPES)],
            'severity' => ['sometimes', 'string', Rule::in(Incident::SEVERITIES)],
            'description' => ['sometimes', 'string'],
            'immediate_action' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(Incident::STATUSES)],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'investigation_notes' => ['nullable', 'string'],
            'corrective_actions' => ['nullable', 'string'],
        ];
    }
}
