<?php

namespace App\Modules\Compliance\Http\Requests;

use App\Modules\Compliance\Models\ComplianceRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplianceRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real tenant-ownership check happens in the controller.
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('complianceRequirement')?->tenant_id;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'jurisdiction' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(ComplianceRequirement::STATUSES)],
            'issued_date' => ['nullable', 'date'],
            'renewal_date' => ['nullable', 'date'],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'responsible_user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }
}
