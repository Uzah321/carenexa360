<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->user()->isPlatformAdmin() || $this->user()->tenant_id === $tenant->id;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
