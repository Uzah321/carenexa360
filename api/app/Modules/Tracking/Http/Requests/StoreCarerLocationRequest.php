<?php

namespace App\Modules\Tracking\Http\Requests;

use App\Modules\Visits\Models\Visit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCarerLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'visit_id' => [
                'nullable',
                'integer',
                Rule::exists((new Visit)->getTable(), 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }
}
