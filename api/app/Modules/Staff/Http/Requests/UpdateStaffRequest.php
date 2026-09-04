<?php

namespace App\Modules\Staff\Http\Requests;

use App\Modules\Staff\Models\StaffProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StaffProfile $staff */
        $staff = $this->route('staff');

        return $this->user()->tenant_id === $staff->tenant_id;
    }

    public function rules(): array
    {
        return [
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'employee_number' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'employment_start_date' => ['nullable', 'date'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:255'],
            'employment_status' => ['nullable', 'string', Rule::in(['active', 'on_leave', 'inactive'])],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
