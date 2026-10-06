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
            'schedule.*' => ['string', 'date_format:H:i'],
            // Leave stock_on_hand empty to not track stock for this medication.
            'stock_on_hand' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'units_per_dose' => ['sometimes', 'numeric', 'min:0.01', 'max:1000'],
            'end_date' => ['nullable', 'date'],
            'instructions' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(Medication::STATUSES)],
        ];
    }
}
