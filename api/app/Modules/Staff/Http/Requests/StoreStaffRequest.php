<?php

namespace App\Modules\Staff\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where('tenant_id', $this->user()->tenant_id),
            ],
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
        ];
    }
}
