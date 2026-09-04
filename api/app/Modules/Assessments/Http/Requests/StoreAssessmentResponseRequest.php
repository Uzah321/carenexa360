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

        return $this->user()->tenant_id === $serviceUser->tenant_id;
    }

    public function rules(): array
    {
        return [
            'assessment_template_id' => [
                'required',
                'integer',
                Rule::exists('assessment_templates', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'answers' => ['required', 'array'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'completed'])],
        ];
    }
}
