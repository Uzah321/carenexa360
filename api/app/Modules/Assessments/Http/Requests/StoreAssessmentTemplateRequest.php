<?php

namespace App\Modules\Assessments\Http\Requests;

use App\Modules\Assessments\Models\AssessmentTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.key' => ['required', 'string', 'max:100'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', 'string', Rule::in(AssessmentTemplate::FIELD_TYPES)],
            'fields.*.options' => ['nullable', 'array'],
            'fields.*.options.*' => ['string', 'max:255'],
            'fields.*.required' => ['nullable', 'boolean'],
        ];
    }
}
