<?php

namespace App\Modules\Medications\Http\Requests;

use App\Modules\Medications\Models\Medication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real tenant-ownership check happens in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'dose' => ['sometimes', 'string', 'max:100'],
            'frequency' => ['sometimes', 'string', 'max:255'],
            'schedule' => ['nullable', 'array'],
            'schedule.*' => ['string'],
            'end_date' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(Medication::STATUSES)],
        ];
    }
}
