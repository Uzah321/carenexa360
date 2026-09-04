<?php

namespace App\Modules\Tracking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckOutDutyPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
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
