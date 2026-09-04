<?php

namespace App\Modules\Training\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTrainingCourseRequest extends FormRequest
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
            'validity_period_months' => ['nullable', 'integer', 'min:1'],
            'is_mandatory' => ['nullable', 'boolean'],
        ];
    }
}
