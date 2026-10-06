<?php

namespace App\Modules\CarePlanning\Support;

use App\Support\RichText;

/**
 * The narrative "Home Care Plan" stored on each care plan version:
 *
 *   about_me, goals_and_outcomes, cognitive_impairment   rich text
 *   desired_outcomes, cognitive_impairment_summary       short text
 *   needs.{area}.details                                 rich text
 *   needs.{area}.consented                               true / false / null (not recorded)
 *   summaries.{key}                                      short text
 *
 * Mirrored in web/src/modules/care-planning/homeCarePlan.ts — keep in step.
 */
class HomeCarePlan
{
    public const RICH_FIELDS = ['about_me', 'goals_and_outcomes', 'cognitive_impairment'];

    public const SHORT_FIELDS = ['desired_outcomes', 'cognitive_impairment_summary'];

    public const NEED_AREAS = [
        'personal_care',
        'continence',
        'mobility',
        'meals',
        'medication',
        'support',
        'other_support',
        'advance_support',
        'final_days',
    ];

    /** Every area except general "support" asks for the client's consent. */
    public const CONSENT_AREAS = [
        'personal_care',
        'continence',
        'mobility',
        'meals',
        'medication',
        'other_support',
        'advance_support',
        'final_days',
    ];

    public const AREA_LABELS = [
        'personal_care' => 'Personal care',
        'continence' => 'Continence care',
        'mobility' => 'Mobility',
        'meals' => 'Meals',
        'medication' => 'Medication support',
        'support' => 'Support',
        'other_support' => 'Other support',
        'advance_support' => 'Advance support',
        'final_days' => 'Final days',
    ];

    public const SUMMARIES = [
        'personal_needs',
        'meal_requirements',
        'dietary_needs',
        'household_support',
        'continence',
        'medication',
        'mobility',
        'mobility_aids',
        'other_support_needs',
    ];

    /** Validation rules, keyed under the given request prefix. */
    public static function rules(string $prefix = 'home_care_plan'): array
    {
        $rules = [
            $prefix => ['nullable', 'array:'.implode(',', [...self::RICH_FIELDS, ...self::SHORT_FIELDS, 'needs', 'summaries'])],
            "{$prefix}.needs" => ['nullable', 'array:'.implode(',', self::NEED_AREAS)],
            "{$prefix}.summaries" => ['nullable', 'array:'.implode(',', self::SUMMARIES)],
        ];

        foreach (self::RICH_FIELDS as $field) {
            $rules["{$prefix}.{$field}"] = ['nullable', 'string', 'max:50000'];
        }
        foreach (self::SHORT_FIELDS as $field) {
            $rules["{$prefix}.{$field}"] = ['nullable', 'string', 'max:1000'];
        }
        foreach (self::NEED_AREAS as $area) {
            $rules["{$prefix}.needs.{$area}"] = ['nullable', 'array:details,consented'];
            $rules["{$prefix}.needs.{$area}.details"] = ['nullable', 'string', 'max:50000'];
            $rules["{$prefix}.needs.{$area}.consented"] = ['nullable', 'boolean'];
        }
        foreach (self::SUMMARIES as $key) {
            $rules["{$prefix}.summaries.{$key}"] = ['nullable', 'string', 'max:1000'];
        }

        return $rules;
    }

    /**
     * Sanitises rich text and drops empty entries, so an untouched form
     * stores nothing rather than a tree of nulls.
     */
    public static function normalize(?array $input): ?array
    {
        if (! $input) {
            return null;
        }

        $plan = [];

        foreach (self::RICH_FIELDS as $field) {
            $plan[$field] = RichText::sanitize($input[$field] ?? null);
        }
        foreach (self::SHORT_FIELDS as $field) {
            $plan[$field] = self::short($input[$field] ?? null);
        }

        $needs = [];
        foreach (self::NEED_AREAS as $area) {
            $details = RichText::sanitize($input['needs'][$area]['details'] ?? null);
            $consented = $input['needs'][$area]['consented'] ?? null;
            if ($details !== null || $consented !== null) {
                $needs[$area] = ['details' => $details, 'consented' => $consented === null ? null : (bool) $consented];
            }
        }
        $plan['needs'] = $needs;

        $summaries = [];
        foreach (self::SUMMARIES as $key) {
            if (($value = self::short($input['summaries'][$key] ?? null)) !== null) {
                $summaries[$key] = $value;
            }
        }
        $plan['summaries'] = $summaries;

        $plan = array_filter($plan, fn ($value) => $value !== null && $value !== []);

        return $plan === [] ? null : $plan;
    }

    protected static function short(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
