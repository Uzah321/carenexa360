<?php

namespace App\Modules\Tracking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckOutDutyPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real check is in the controller — only the duty period's own
        // owner can check it out, tenant or not.
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
        ];
    }
}
