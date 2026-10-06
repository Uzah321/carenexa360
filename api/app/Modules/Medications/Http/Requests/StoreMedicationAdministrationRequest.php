<?php

namespace App\Modules\Medications\Http\Requests;

use App\Modules\Medications\Models\MedicationAdministration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMedicationAdministrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real tenant-ownership check happens in the controller (against
        // the medication this administration record belongs to).
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('medication')?->tenant_id;

        return [
            'visit_id' => [
                'nullable',
                'integer',
                Rule::exists('visits', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => ['required', 'string', Rule::in(MedicationAdministration::STATUSES)],
            'scheduled_time' => ['nullable', 'date_format:H:i'],
            'not_given_reason' => [
                'nullable',
                'required_if:status,not_given',
                'prohibited_unless:status,not_given',
                Rule::in(MedicationAdministration::NOT_GIVEN_REASONS),
            ],
            'stock_checked' => ['nullable', 'boolean'],
            'administered_at' => ['nullable', 'date'],
            'witness_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $medication = $this->route('medication');

            if (
                $medication
                && $medication->is_controlled_drug
                && in_array($this->input('status'), ['administered', 'prn'], true)
                && ! $this->filled('witness_id')
            ) {
                $validator->errors()->add(
                    'witness_id',
                    'A witness is required when administering a controlled drug.'
                );
            }
        });
    }
}
