<?php

namespace App\Modules\Observations\Http\Requests;

use App\Modules\Observations\Models\Observation;
use App\Modules\Observations\Support\News2;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'value.on_oxygen' => ['sometimes', 'boolean'],
            'value.spo2_scale' => ['sometimes', 'integer', Rule::in([1, 2])],
            'value.consciousness' => ['sometimes', 'string', Rule::in(News2::CONSCIOUSNESS_LEVELS)],
            ...($this->route('observation')?->type === 'wound' ? Observation::woundRules('sometimes') : []),
            'unit' => ['nullable', 'string', 'max:50'],
            'recorded_at' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
