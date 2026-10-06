<?php

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Models\Tenant;

/**
 * Every organisation-level setting in one place: its default, and how it's
 * validated. Stored in the tenants.settings JSON blob; anything not saved
 * falls back to the default here, so reading a setting always gives a
 * usable value (see Tenant::setting()).
 *
 * Mirrored in web/src/modules/settings/defaults.ts — keep the two in step.
 */
class TenantSettings
{
    public const DEFAULTS = [
        // Visits & GPS
        'geofence_radius_meters' => 100,
        'late_arrival_minutes' => 10,

        // Workforce
        'overtime_weekly_hours' => 40,
        'mileage_rate_per_mile' => 0.45,
        'training_expiry_warning_days' => 30,

        // Medication
        'medication_window_minutes' => 60,
        'stock_reorder_days' => 7,

        // Quality
        'complaint_response_days' => 28,

        // Security
        'session_timeout_minutes' => null,

        // Timescales a client's care should move through.
        'care_pathway' => [
            'assessment_within_days' => 3,
            'care_plan_within_days' => 7,
            'first_review_within_weeks' => 6,
            'review_interval_months' => 6,
            'risk_review_interval_months' => 3,
        ],

        // Choices offered wherever these are entered. Free text is still
        // accepted — these are suggestions, not a closed list.
        'reference_data' => [
            'care_tasks' => [
                'Personal care', 'Morning wash', 'Bathing/showering', 'Dressing', 'Continence care', 'Meal preparation',
                'Medication prompt', 'Medication administration', 'Mobility support', 'Companionship',
                'Light housekeeping', 'Shopping', 'Wound care', 'Clinical observations',
            ],
            'skills' => [
                'Personal Care', 'Medication Administration', 'Manual Handling', 'Dementia Care', 'Wound Care',
                'Catheter Care', 'PEG Feeding', 'Diabetes Care', 'End of Life Care', 'Clinical Observations',
            ],
            'job_titles' => ['Carer', 'Senior Carer', 'Nurse', 'Care Coordinator', 'Care Manager', 'Branch Manager'],
            'staff_document_categories' => ['DBS check', 'Right to work', 'Contract', 'ID', 'References', 'Training certificate'],
            'client_document_categories' => ['Hospital Record', 'Consent form', 'Signed care plan', 'Assessment', 'DNACPR / ReSPECT', 'Correspondence'],
            'medication_routes' => ['Oral', 'Topical', 'Inhaled', 'Transdermal patch', 'Subcutaneous injection', 'Eye drops', 'Ear drops', 'Nasal', 'Rectal', 'PEG'],
            'medication_forms' => ['Tablet', 'Capsule', 'Liquid', 'Cream', 'Ointment', 'Inhaler', 'Patch', 'Drops', 'Injection'],
            'equipment' => ['Walking frame', 'Walking stick', 'Wheelchair', 'Hoist', 'Slide sheet', 'Commode', 'Shower chair', 'Pressure-relieving mattress', 'Gait belt'],
        ],
    ];

    public const REFERENCE_LISTS = [
        'care_tasks', 'skills', 'job_titles', 'staff_document_categories', 'client_document_categories',
        'medication_routes', 'medication_forms', 'equipment',
    ];

    /** Validation rules for a settings update (all optional — only what's sent is saved). */
    public static function rules(): array
    {
        $rules = [
            'settings' => ['sometimes', 'array'],
            'settings.geofence_radius_meters' => ['sometimes', 'integer', 'min:10', 'max:2000'],
            'settings.late_arrival_minutes' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'settings.overtime_weekly_hours' => ['sometimes', 'numeric', 'min:1', 'max:100'],
            'settings.mileage_rate_per_mile' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'settings.training_expiry_warning_days' => ['sometimes', 'integer', 'min:1', 'max:180'],
            'settings.medication_window_minutes' => ['sometimes', 'integer', 'min:5', 'max:240'],
            'settings.stock_reorder_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'settings.complaint_response_days' => ['sometimes', 'integer', 'min:1', 'max:120'],
            'settings.session_timeout_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:1440'],

            'settings.care_pathway' => ['sometimes', 'array:'.implode(',', array_keys(self::DEFAULTS['care_pathway']))],
            'settings.care_pathway.assessment_within_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'settings.care_pathway.care_plan_within_days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'settings.care_pathway.first_review_within_weeks' => ['sometimes', 'integer', 'min:1', 'max:52'],
            'settings.care_pathway.review_interval_months' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'settings.care_pathway.risk_review_interval_months' => ['sometimes', 'integer', 'min:1', 'max:24'],

            'settings.reference_data' => ['sometimes', 'array:'.implode(',', self::REFERENCE_LISTS)],
        ];

        foreach (self::REFERENCE_LISTS as $list) {
            $rules["settings.reference_data.{$list}"] = ['sometimes', 'array', 'max:200'];
            $rules["settings.reference_data.{$list}.*"] = ['string', 'distinct', 'max:100'];
        }

        return $rules;
    }

    /**
     * Saved settings layered over the defaults — what the app should
     * actually use. Nested groups (care_pathway, reference_data) merge per
     * key, so saving one value never loses the others' defaults.
     */
    public static function effective(?array $saved): array
    {
        $saved ??= [];
        $effective = array_replace(self::DEFAULTS, $saved);

        foreach (['care_pathway', 'reference_data'] as $group) {
            $effective[$group] = array_replace(self::DEFAULTS[$group], $saved[$group] ?? []);
        }

        return $effective;
    }

    /** @var array<int, array> Per-request cache, keyed by tenant id. */
    private static array $cache = [];

    /** A tenant's effective setting, cached for the rest of the request. */
    public static function for(?int $tenantId, string $key): mixed
    {
        if (! $tenantId) {
            return data_get(self::DEFAULTS, $key);
        }

        self::$cache[$tenantId] ??= self::effective(Tenant::find($tenantId)?->settings);

        return data_get(self::$cache[$tenantId], $key);
    }

    /** Drop cached values — call after a tenant's settings change. */
    public static function forget(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$tenantId]);
        }
    }
}
