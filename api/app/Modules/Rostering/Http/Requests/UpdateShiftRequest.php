<?php

namespace App\Modules\Rostering\Http\Requests;

use App\Modules\Rostering\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Shift $shift */
        $shift = $this->route('shift');

        return $this->user()->ownsTenant($shift->tenant_id);
    }

    public function rules(): array
    {
        return [
            'shift_date' => ['sometimes', 'required', 'date'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],
            'shift_type' => ['nullable', 'string', Rule::in(Shift::TYPES)],
            'status' => ['nullable', 'string', Rule::in(Shift::STATUSES)],
            'notes' => ['nullable', 'string'],
        ];
    }
}
