<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'service_user_id' => [
                'required',
                'integer',
                Rule::exists('service_users', 'id')->where('tenant_id', $tenantId),
            ],
            'funder_id' => [
                'nullable',
                'integer',
                Rule::exists('funders', 'id')->where('tenant_id', $tenantId),
            ],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date', 'after_or_equal:period_end'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
