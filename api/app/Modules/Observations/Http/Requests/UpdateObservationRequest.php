<?php

namespace App\Modules\Observations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real tenant-ownership check happens in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            // Deliberately excludes `type` — it defines the shape of `value`
            // (and which threshold check runs against it), so changing it on
            // an existing reading would leave stale, mismatched data instead
            // of a corrected one. Record a new observation for a different type.
            'value' => ['sometimes', 'array'],
            'unit' => ['nullable', 'string', 'max:50'],
            'recorded_at' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
