<?php

namespace App\Modules\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real tenant-ownership check happens in the controller once the
        // service user named by service_user_id is resolved.
        return true;
    }

    public function rules(): array
    {
        // A platform admin's own tenant_id is null and can't scope these —
        // for them, existence alone is checked; the controller's ownsTenant
        // check catches anything that still shouldn't be allowed.
        $tenantId = $this->user()->tenant_id;

        return [
            'service_user_id' => [
                'required',
                'integer',
                Rule::exists('service_users', 'id')->when(
                    $tenantId !== null,
                    fn (\Illuminate\Validation\Rules\Exists $rule) => $rule->where('tenant_id', $tenantId),
                ),
            ],
            'funder_id' => [
                'nullable',
                'integer',
                Rule::exists('funders', 'id')->when(
                    $tenantId !== null,
                    fn (\Illuminate\Validation\Rules\Exists $rule) => $rule->where('tenant_id', $tenantId),
                ),
            ],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date', 'after_or_equal:period_end'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
