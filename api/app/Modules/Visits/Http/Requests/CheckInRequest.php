<?php

namespace App\Modules\Visits\Http\Requests;

use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Visit $visit */
        $visit = $this->route('visit');

        return $this->user()->tenant_id === $visit->tenant_id;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'override_reason' => ['nullable', 'string'],
        ];
    }
}
