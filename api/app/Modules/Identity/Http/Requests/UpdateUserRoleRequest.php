<?php

namespace App\Modules\Identity\Http\Requests;

use App\Models\User;
use App\Modules\Identity\Support\AdministrationRoles;
use App\Modules\Identity\Support\DefaultRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $targetUser */
        $targetUser = $this->route('user');

        if ($this->user()->isPlatformAdmin()) {
            return true;
        }

        return $this->user()->tenant_id === $targetUser->tenant_id
            && $this->user()->hasAnyRole(AdministrationRoles::ALLOWED);
    }

    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in(DefaultRoles::TENANT_ROLES)],
        ];
    }
}
