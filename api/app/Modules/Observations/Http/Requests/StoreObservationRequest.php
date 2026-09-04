<?php

namespace App\Modules\Observations\Http\Requests;

use App\Modules\Observations\Models\Observation;
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

        return [
            'visit_id' => [
                'nullable',
                'integer',
                Rule::exists('visits', 'id')->where('tenant_id', $tenantId),
            ],
            'type' => ['required', 'string', Rule::in(Observation::TYPES)],
            'value' => ['required', 'array'],
            'unit' => ['nullable', 'string', 'max:50'],
            'recorded_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
