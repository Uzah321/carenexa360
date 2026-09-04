<?php

namespace App\Modules\Visits\Http\Requests;

use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()->isPlatformAdmin() || $this->user()->tenant_id === $visit->tenant_id;
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'carer_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'visit_date' => ['sometimes', 'required', 'date'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],
            'care_tasks' => ['nullable', 'array'],
            'care_tasks.*' => ['string', 'max:255'],
            'completed_care_tasks' => ['nullable', 'array'],
            'completed_care_tasks.*' => ['string', 'max:255'],
            'medication_tasks' => ['nullable', 'boolean'],
            'medication_tasks_completed' => ['nullable', 'boolean'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:255'],
            'priority' => ['nullable', 'string', Rule::in(Visit::PRIORITIES)],
            'status' => ['nullable', 'string', Rule::in(Visit::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
