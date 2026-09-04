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
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'visit_id' => [
                'nullable',
                'integer',
                Rule::exists('visits', 'id')->where('tenant_id', $tenantId),
            ],
            'status' => ['required', 'string', Rule::in(MedicationAdministration::STATUSES)],
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
                && $this->input('status') === 'administered'
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
