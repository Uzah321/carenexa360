<?php

namespace App\Modules\Documents\Http\Requests;

use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
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
            'file' => ['required', 'file', 'max:20480'],
            'category' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
            'visible_to_family' => ['nullable', 'boolean'],
        ];
    }
}
