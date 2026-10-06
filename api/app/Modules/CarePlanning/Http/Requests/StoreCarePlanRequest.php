<?php

namespace App\Modules\CarePlanning\Http\Requests;

use App\Modules\CarePlanning\Models\CarePlanRiskAssessment;
use App\Modules\CarePlanning\Models\CarePlanSection;
use App\Modules\CarePlanning\Support\HomeCarePlan;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCarePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ServiceUser $serviceUser */
        $serviceUser = $this->route('serviceUser');

        return $this->user()->ownsTenant($serviceUser->tenant_id);
    }

    public function rules(): array
    {
        /** @var ServiceUser $serviceUser */
        $serviceUser = $this->route('serviceUser');

        return [
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            ...HomeCarePlan::rules(),
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.area' => ['required', 'string', Rule::in(CarePlanSection::AREAS)],
            'sections.*.identified_need' => ['required', 'string'],
            'sections.*.risk' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
            'sections.*.goal' => ['required', 'string'],
            'sections.*.intervention' => ['required', 'string'],
            'sections.*.equipment' => ['nullable', 'string'],
            'sections.*.frequency' => ['nullable', 'string', 'max:255'],
            'sections.*.responsible_staff_id' => [
                'nullable',
                'integer',
                // Scoped to the service user's tenant, not the acting user's
                // — see StoreAssessmentResponseRequest for why.
                Rule::exists('users', 'id')->where('tenant_id', $serviceUser->tenant_id),
            ],
            'sections.*.start_date' => ['nullable', 'date'],
            'sections.*.review_date' => ['nullable', 'date'],
            'sections.*.status' => ['nullable', 'string', Rule::in(['ongoing', 'met', 'discontinued'])],
            'sections.*.notes' => ['nullable', 'string'],

            'risk_assessments' => ['nullable', 'array'],
            'risk_assessments.*.type' => ['required', 'string', Rule::in(CarePlanRiskAssessment::TYPES)],
            'risk_assessments.*.area' => ['nullable', 'string', Rule::in(CarePlanSection::AREAS)],
            'risk_assessments.*.risk_type' => ['nullable', 'string', Rule::in(CarePlanRiskAssessment::RISK_TYPES)],
            'risk_assessments.*.hazard' => ['required', 'string'],
            'risk_assessments.*.details' => ['nullable', 'string', 'max:50000'],
            'risk_assessments.*.triggers' => ['nullable', 'string'],
            'risk_assessments.*.persons_at_risk' => ['nullable', 'array'],
            'risk_assessments.*.persons_at_risk.*' => ['string', Rule::in(CarePlanRiskAssessment::PERSONS_AT_RISK)],
            'risk_assessments.*.harm_description' => ['nullable', 'string'],
            'risk_assessments.*.likelihood' => ['nullable', 'integer', 'between:1,5'],
            'risk_assessments.*.severity' => ['nullable', 'integer', 'between:1,5'],
            'risk_assessments.*.existing_controls' => ['nullable', 'string'],
            'risk_assessments.*.further_actions' => ['nullable', 'string'],
            'risk_assessments.*.residual_likelihood' => ['nullable', 'integer', 'between:1,5'],
            'risk_assessments.*.residual_severity' => ['nullable', 'integer', 'between:1,5'],
            'risk_assessments.*.target_likelihood' => ['nullable', 'integer', 'between:1,5'],
            'risk_assessments.*.target_severity' => ['nullable', 'integer', 'between:1,5'],
            'risk_assessments.*.contingency_plan_required' => ['nullable', 'boolean'],
            'risk_assessments.*.contingency_plan' => ['nullable', 'string', 'max:50000'],
            'risk_assessments.*.action_owner_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $serviceUser->tenant_id),
            ],
            'risk_assessments.*.action_due_date' => ['nullable', 'date'],
            'risk_assessments.*.review_date' => ['nullable', 'date'],
            'risk_assessments.*.medication_details' => ['nullable', 'array:'.implode(',', CarePlanRiskAssessment::MEDICATION_DETAIL_KEYS)],
            'risk_assessments.*.medication_details.medication_name' => ['nullable', 'string', 'max:255'],
            'risk_assessments.*.medication_details.dose_route_frequency' => ['nullable', 'string', 'max:255'],
            'risk_assessments.*.medication_details.support_level' => ['nullable', 'string', Rule::in(CarePlanRiskAssessment::MEDICATION_SUPPORT_LEVELS)],
            'risk_assessments.*.medication_details.controlled_drug' => ['nullable', 'boolean'],
            'risk_assessments.*.medication_details.prn' => ['nullable', 'boolean'],
            'risk_assessments.*.medication_details.capacity_and_consent' => ['nullable', 'string'],
            'risk_assessments.*.medication_details.storage' => ['nullable', 'string'],
            'risk_assessments.*.medication_details.prn_protocol' => ['nullable', 'string'],
            'risk_assessments.*.medication_details.side_effects_to_monitor' => ['nullable', 'string'],
            'risk_assessments.*.medication_details.known_allergies' => ['nullable', 'string'],
            'risk_assessments.*.medication_details.ordering_and_collection' => ['nullable', 'string'],
            'risk_assessments.*.medication_details.disposal' => ['nullable', 'string'],
            'risk_assessments.*.medication_details.error_response' => ['nullable', 'string'],
        ];
    }
}
