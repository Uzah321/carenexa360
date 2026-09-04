<?php

namespace App\Modules\Visits\Http\Requests;

use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitRequest extends FormRequest
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
                'required',
                'integer',
                Rule::exists('service_users', 'id')->where('tenant_id', $tenantId),
            ],
            'carer_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'visit_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'care_tasks' => ['nullable', 'array'],
            'care_tasks.*' => ['string', 'max:255'],
            'medication_tasks' => ['nullable', 'boolean'],
            'required_skills' => ['nullable', 'array'],
            'required_skills.*' => ['string', 'max:255'],
            'priority' => ['nullable', 'string', Rule::in(Visit::PRIORITIES)],
            'notes' => ['nullable', 'string'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.weekdays' => ['required_with:recurrence', 'array', 'min:1'],
            'recurrence.weekdays.*' => ['integer', 'between:0,6'],
            'recurrence.until' => ['required_with:recurrence', 'date', 'after_or_equal:visit_date'],
        ];
    }
}
