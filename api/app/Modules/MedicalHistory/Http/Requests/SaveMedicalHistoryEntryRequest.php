<?php

namespace App\Modules\MedicalHistory\Http\Requests;

use App\Modules\MedicalHistory\Models\MedicalHistoryEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create (POST, category and name required) and update (PATCH, everything optional). */
class SaveMedicalHistoryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real tenant-ownership check happens in the controller.
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'category' => [$required, 'string', Rule::in(MedicalHistoryEntry::CATEGORIES)],
            'name' => [$required, 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(MedicalHistoryEntry::STATUSES)],
            'severity' => ['nullable', 'string', Rule::in(MedicalHistoryEntry::SEVERITIES)],
            'allergy_type' => ['nullable', 'string', Rule::in(MedicalHistoryEntry::ALLERGY_TYPES)],
            'reaction' => ['nullable', 'string', 'max:2000'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'started_on' => ['nullable', 'date'],
            // Only compared when a start date is sent alongside it — on a
            // PATCH without one there's nothing in the request to compare to.
            'ended_on' => ['nullable', 'date', Rule::when($this->filled('started_on'), 'after_or_equal:started_on')],
            'provider' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'ended_on.after_or_equal' => 'The end date can\'t be before the start date.',
        ];
    }
}
