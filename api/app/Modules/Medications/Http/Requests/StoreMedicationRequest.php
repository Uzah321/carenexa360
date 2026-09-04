<?php

namespace App\Modules\Medications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real tenant-ownership check happens in the controller (against
        // the service user this medication belongs to) — this request has no
        // route param to check against, and blocking platform admins here
        // unconditionally would leave them permanently unable to reach it.
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'strength' => ['nullable', 'string', 'max:100'],
            'form' => ['nullable', 'string', 'max:100'],
            'dose' => ['required', 'string', 'max:100'],
            'route' => ['required', 'string', 'max:100'],
            'frequency' => ['required', 'string', 'max:255'],
            'schedule' => ['nullable', 'array'],
            'schedule.*' => ['string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'prescriber' => ['nullable', 'string', 'max:255'],
            'pharmacy' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'is_prn' => ['nullable', 'boolean'],
            'prn_instructions' => ['nullable', 'string', 'required_if:is_prn,true'],
            'is_controlled_drug' => ['nullable', 'boolean'],
        ];
    }
}
