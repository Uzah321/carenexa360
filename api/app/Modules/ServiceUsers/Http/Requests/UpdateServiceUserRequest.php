<?php

namespace App\Modules\ServiceUsers\Http\Requests;

use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceUserRequest extends FormRequest
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
            'branch_id' => [
                'nullable',
                'integer',
                // Scoped to the service user's tenant, not the acting user's
                // — see StoreAssessmentResponseRequest for why.
                Rule::exists('branches', 'id')->where('tenant_id', $serviceUser->tenant_id),
            ],
            'care_manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $serviceUser->tenant_id),
            ],
            // The client's care team — staff only (anyone with a staff profile
            // in this tenant), never family-portal accounts.
            'carer_ids' => ['sometimes', 'array'],
            'carer_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('staff_profiles', 'user_id')->where('tenant_id', $serviceUser->tenant_id),
            ],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'nhs_number' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'discharged'])],
            // Allergies, diagnoses, conditions and disabilities aren't set
            // here — they're recorded as medical history entries, which keep
            // the client's summary lists up to date (see MedicalSummary).
            'no_known_allergies' => ['sometimes', 'boolean'],
            'smoking_status' => ['nullable', 'string', Rule::in(ServiceUser::SMOKING_STATUSES)],
            'alcohol_intake' => ['nullable', 'string', 'max:255'],
            'resuscitation_status' => ['nullable', 'string', Rule::in(ServiceUser::RESUSCITATION_STATUSES)],
            'resuscitation_notes' => ['nullable', 'string'],
            'medical_history_summary' => ['nullable', 'string'],
            'mobility_notes' => ['nullable', 'string'],
            'communication_needs' => ['nullable', 'string'],
            'dietary_needs' => ['nullable', 'string'],
            'cultural_preferences' => ['nullable', 'string'],
            'religious_requirements' => ['nullable', 'string'],
            'behavioural_considerations' => ['nullable', 'string'],
            'preferred_routines' => ['nullable', 'string'],
            'capacity_consent_notes' => ['nullable', 'string'],
            'referring_hospital' => ['nullable', 'string', 'max:255'],
            'hospital_record_number' => ['nullable', 'string', 'max:255'],
            'discharge_date' => ['nullable', 'date'],
            'discharge_summary' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var ServiceUser $serviceUser */
                $serviceUser = $this->route('serviceUser');

                if ($this->boolean('no_known_allergies') && $serviceUser->currentAllergies()->exists()) {
                    $validator->errors()->add(
                        'no_known_allergies',
                        'This client has allergies on record — mark them resolved before confirming no known allergies.',
                    );
                }
            },
        ];
    }
}
