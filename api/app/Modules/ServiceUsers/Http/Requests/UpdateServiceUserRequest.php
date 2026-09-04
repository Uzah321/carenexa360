<?php

namespace App\Modules\ServiceUsers\Http\Requests;

use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceUserRequest extends FormRequest
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
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'care_manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $this->user()->tenant_id),
            ],
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name' => ['sometimes', 'required', 'string', 'max:255'],
            'preferred_name' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'funding_source' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'discharged'])],
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:255'],
            'diagnoses' => ['nullable', 'array'],
            'diagnoses.*' => ['string', 'max:255'],
            'medical_conditions' => ['nullable', 'array'],
            'medical_conditions.*' => ['string', 'max:255'],
            'disabilities' => ['nullable', 'array'],
            'disabilities.*' => ['string', 'max:255'],
            'mobility_notes' => ['nullable', 'string'],
            'communication_needs' => ['nullable', 'string'],
            'dietary_needs' => ['nullable', 'string'],
            'cultural_preferences' => ['nullable', 'string'],
            'religious_requirements' => ['nullable', 'string'],
            'behavioural_considerations' => ['nullable', 'string'],
            'preferred_routines' => ['nullable', 'string'],
            'capacity_consent_notes' => ['nullable', 'string'],
        ];
    }
}
