<?php

namespace App\Modules\Compliance\Http\Requests;

use App\Modules\Compliance\Models\ComplianceRequirement;
use App\Modules\Training\Support\ComplianceRoles;
use Illuminate\Foundation\Http\FormRequest;

class StoreComplianceDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ComplianceRequirement $complianceRequirement */
        $complianceRequirement = $this->route('complianceRequirement');

        return $this->user()->hasAnyRole(ComplianceRoles::ALLOWED)
            && $this->user()->ownsTenant($complianceRequirement->tenant_id);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'],
            'category' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
        ];
    }
}
