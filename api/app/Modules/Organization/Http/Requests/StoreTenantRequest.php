<?php

namespace App\Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', 'unique:tenants,slug'],
            'country' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'max:255'],
            'currency' => ['required', 'string', 'max:10'],
            'locale' => ['required', 'string', 'max:10'],
        ];
    }
}
