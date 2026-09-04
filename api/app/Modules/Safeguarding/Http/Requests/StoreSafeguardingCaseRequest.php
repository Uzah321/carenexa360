<?php

namespace App\Modules\Safeguarding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSafeguardingCaseRequest extends FormRequest
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
            'victim_name' => ['nullable', 'string', 'max:255'],
            'alleged_perpetrator' => ['nullable', 'string'],
            'concern_type' => ['required', 'string', 'max:255'],
            'immediate_risk' => ['required', 'boolean'],
            'external_agencies_notified' => ['nullable', 'string'],
            'confidential_notes' => ['nullable', 'string'],
        ];
    }
}
