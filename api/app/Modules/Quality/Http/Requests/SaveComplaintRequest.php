<?php

namespace App\Modules\Quality\Http\Requests;

use App\Modules\Quality\Models\Complaint;
use App\Modules\Quality\Support\QualityRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create (POST, core fields required) and update (PATCH, everything optional). */
class SaveComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(QualityRoles::ALLOWED);
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'service_user_id' => ['nullable', 'integer', Rule::exists('service_users', 'id')->where('tenant_id', $tenantId)],
            'received_date' => [$required, 'date'],
            'complainant_name' => [$required, 'string', 'max:255'],
            'complainant_relationship' => ['nullable', 'string', 'max:255'],
            'channel' => ['sometimes', 'string', Rule::in(Complaint::CHANNELS)],
            'category' => [$required, 'string', Rule::in(Complaint::CATEGORIES)],
            'severity' => ['sometimes', 'string', Rule::in(Complaint::SEVERITIES)],
            'description' => [$required, 'string'],
            'status' => ['sometimes', 'string', Rule::in(Complaint::STATUSES)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'acknowledged_date' => ['nullable', 'date'],
            'response_due_date' => ['nullable', 'date'],
            'outcome' => ['nullable', 'string', Rule::in(Complaint::OUTCOMES)],
            'findings' => ['nullable', 'string'],
            'actions_taken' => ['nullable', 'string'],
            'resolved_date' => ['nullable', 'date'],
        ];
    }
}
