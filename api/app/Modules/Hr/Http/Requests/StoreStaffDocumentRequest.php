<?php

namespace App\Modules\Hr\Http\Requests;

use App\Modules\Staff\Models\StaffProfile;
use Illuminate\Foundation\Http\FormRequest;

class StoreStaffDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var StaffProfile $staff */
        $staff = $this->route('staff');

        return $this->user()->ownsTenant($staff->tenant_id);
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'],
            'category' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
        ];
    }
}
