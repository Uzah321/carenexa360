<?php

namespace App\Modules\Training\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'training_course_id' => [
                'required',
                'integer',
                Rule::exists('training_courses', 'id')->where('tenant_id', $tenantId),
            ],
            'completed_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:completed_date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
