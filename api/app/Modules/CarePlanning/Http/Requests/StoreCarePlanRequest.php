<?php

namespace App\Modules\CarePlanning\Http\Requests;

use App\Modules\CarePlanning\Models\CarePlanSection;
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
        ];
    }
}
