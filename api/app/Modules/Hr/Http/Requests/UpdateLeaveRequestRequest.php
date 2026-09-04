<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Models\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(LeaveRequest::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
