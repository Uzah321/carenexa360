<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Support\AdministrationRoles;
use App\Modules\Identity\Support\DefaultRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin()
            && $this->user()->hasAnyRole(AdministrationRoles::ALLOWED);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(DefaultRoles::TENANT_ROLES)],
        ];
    }
}
