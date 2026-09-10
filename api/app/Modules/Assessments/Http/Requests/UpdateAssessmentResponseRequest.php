<?php

namespace App\Modules\Assessments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssessmentResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real tenant-ownership check happens in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => ['sometimes', 'array'],
            'status' => ['sometimes', 'string', Rule::in(['draft', 'completed'])],
        ];
    }
}
