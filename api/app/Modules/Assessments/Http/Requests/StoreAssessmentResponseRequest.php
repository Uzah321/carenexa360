<?php

namespace App\Modules\Assessments\Http\Requests;

use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ServiceUser $serviceUser */
        $serviceUser = $this->route('serviceUser');

        return $this->user()->ownsTenant($serviceUser->tenant_id);
    }

    public function rules(): array
    {
        /** @var ServiceUser $serviceUser */
        $serviceUser = $this->route('serviceUser');

        return [
            'assessment_template_id' => [
                'required',
                'integer',
                // Scoped to the service user's own tenant, not the acting
                // user's — a platform admin's tenant_id is null, so scoping
                // to their own would make every template look nonexistent.
                Rule::exists('assessment_templates', 'id')->where('tenant_id', $serviceUser->tenant_id),
            ],
            'answers' => ['required', 'array'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'completed'])],
        ];
    }
}
