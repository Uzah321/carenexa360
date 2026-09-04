<?php

namespace App\Modules\ServiceUsers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantPortalAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->user()->isPlatformAdmin();
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
