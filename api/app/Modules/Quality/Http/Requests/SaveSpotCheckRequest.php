<?php

namespace App\Modules\Quality\Http\Requests;

use App\Modules\Quality\Models\SpotCheck;
use App\Modules\Quality\Support\QualityRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create (POST, core fields required) and update (PATCH, everything optional). */
class SaveSpotCheckRequest extends FormRequest
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
            'staff_user_id' => [$required, 'integer', Rule::exists('staff_profiles', 'user_id')->where('tenant_id', $tenantId)],
            'service_user_id' => ['nullable', 'integer', Rule::exists('service_users', 'id')->where('tenant_id', $tenantId)],
            'visit_id' => ['nullable', 'integer', Rule::exists('visits', 'id')->where('tenant_id', $tenantId)],
            'check_date' => [$required, 'date'],
            'results' => [$required, 'array:'.implode(',', SpotCheck::AREAS)],
            'results.*' => ['string', Rule::in(SpotCheck::RESULTS)],
            'notes' => ['nullable', 'string'],
            'actions_required' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
        ];
    }
}
