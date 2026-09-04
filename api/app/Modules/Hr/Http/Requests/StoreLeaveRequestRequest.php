<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Hr\Models\LeaveRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(LeaveRequest::TYPES)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
