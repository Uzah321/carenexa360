<?php

namespace App\Modules\Observations\Http\Requests;

use App\Modules\Observations\Models\Observation;
use App\Modules\Observations\Support\News2;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real tenant-ownership check happens in the controller (against
        // the service user this observation belongs to).
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->route('serviceUser')?->tenant_id;
        $isNews2Set = $this->input('type') === 'news2';

        return [
            'visit_id' => [
                'nullable',
                'integer',
                Rule::exists('visits', 'id')->where('tenant_id', $tenantId),
            ],
            'type' => ['required', 'string', Rule::in(Observation::TYPES)],
            'value' => ['required', 'array'],
            // NEWS2 inputs. SpO2 readings can say whether oxygen was in use and
            // which SpO2 scale applies; a full `news2` set needs every parameter.
            'value.on_oxygen' => [Rule::requiredIf($isNews2Set), 'boolean'],
            'value.spo2_scale' => ['sometimes', 'integer', Rule::in([1, 2])],
            'value.respiration_rate' => [Rule::requiredIf($isNews2Set), 'numeric', 'between:0,80'],
            'value.spo2' => [Rule::requiredIf($isNews2Set), 'numeric', 'between:0,100'],
            'value.systolic' => [Rule::requiredIf($isNews2Set), 'numeric', 'between:0,300'],
            'value.pulse' => [Rule::requiredIf($isNews2Set), 'numeric', 'between:0,300'],
            'value.consciousness' => [Rule::requiredIf($isNews2Set), 'string', Rule::in(News2::CONSCIOUSNESS_LEVELS)],
            'value.temperature' => [Rule::requiredIf($isNews2Set), 'numeric', 'between:25,45'],
            ...($this->input('type') === 'wound' ? Observation::woundRules('required') : []),
            'unit' => ['nullable', 'string', 'max:50'],
            'recorded_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
