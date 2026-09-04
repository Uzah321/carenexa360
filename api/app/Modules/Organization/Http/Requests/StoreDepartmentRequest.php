<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return $this->user()->isPlatformAdmin() || $this->user()->tenant_id === $tenant->id;
    }

    public function rules(): array
    {
        /** @var Tenant $tenant */
        $tenant = $this->route('tenant');

        return [
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => [
                'required',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $tenant->id),
            ],
        ];
    }
}
