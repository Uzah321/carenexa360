<?php

namespace App\Modules\ServiceUsers\Http\Requests;

use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\ServiceUsers\Models\ServiceUserContact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceUserContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ServiceUser $serviceUser */
        $serviceUser = $this->route('serviceUser');

        return $this->user()->tenant_id === $serviceUser->tenant_id;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(ServiceUserContact::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
