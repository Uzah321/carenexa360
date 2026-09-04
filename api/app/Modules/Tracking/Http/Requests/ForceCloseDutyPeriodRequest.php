<?php

namespace App\Modules\Tracking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForceCloseDutyPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        return [
            // Required, and long enough to be an actual explanation — this is the
            // only record of why a shift has no carer check-out.
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Give a reason for closing this shift without the carer checking out.',
            'reason.min' => 'The reason needs to be a little more specific.',
        ];
    }
}
