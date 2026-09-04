<?php

namespace App\Modules\ServiceUsers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantPortalAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real tenant-ownership check happens in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
