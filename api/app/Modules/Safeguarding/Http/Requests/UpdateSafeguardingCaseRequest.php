<?php

namespace App\Modules\Safeguarding\Http\Requests;

use App\Modules\Safeguarding\Models\SafeguardingCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSafeguardingCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The real tenant-ownership check happens in the controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(SafeguardingCase::STATUSES)],
            'investigation_notes' => ['nullable', 'string'],
            'actions_taken' => ['nullable', 'string'],
            'outcome' => ['nullable', 'string'],
            'external_agencies_notified' => ['nullable', 'string'],
            'confidential_notes' => ['nullable', 'string'],
        ];
    }
}
