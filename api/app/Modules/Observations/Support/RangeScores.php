<?php

namespace App\Modules\Observations\Support;

/**
 * 0–3 out-of-range scores for measurements NEWS2 doesn't cover, using the
 * same shape and meaning as News2's parameter scores (0 = normal range,
 * 3 = red zone) so they display and alert the same way.
 *
 * These are this app's own bands, not a national standard, and they are
 * deliberately kept out of the NEWS2 total — adding them in would make the
 * total stop meaning what the RCP chart says it means.
 *
 * Mirrored in web/src/modules/observations/rangeScores.ts — keep in step.
 */
class RangeScores
{
    /**
     * @return array<string, array{parameter: string, label: string, reading: string, score: int, direction: string}>
     */
    public static function parameterScores(string $type, array $value): array
    {
        $scored = match ($type) {
            'blood_pressure' => ['diastolic' => [self::scoreDiastolic($value['diastolic'] ?? null), $value['diastolic'] ?? null, 'Diastolic blood pressure', 'mmHg']],
            'blood_glucose' => ['blood_glucose' => [self::scoreBloodGlucose($value['value'] ?? null), $value['value'] ?? null, 'Blood glucose', 'mg/dL']],
            default => [],
        };

        $scores = [];
        foreach ($scored as $parameter => [$result, $reading, $label, $unit]) {
            if ($result === null) {
                continue;
            }
            $scores[$parameter] = [
                'parameter' => $parameter,
                'label' => $label,
                'reading' => trim("{$reading} {$unit}"),
                ...$result,
            ];
        }

        return $scores;
    }

    /** Normal range 60–120 mmHg. */
    public static function scoreDiastolic(mixed $value): ?array
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return match (true) {
            $value <= 40 => ['score' => 3, 'direction' => 'low'],
            $value < 50 => ['score' => 2, 'direction' => 'low'],
            $value < 60 => ['score' => 1, 'direction' => 'low'],
            $value <= 120 => ['score' => 0, 'direction' => 'normal'],
            // Above 120 is hypertensive-crisis territory — red zone outright.
            default => ['score' => 3, 'direction' => 'high'],
        };
    }

    /**
     * Normal range 70–250 mg/dL. Below 54 mg/dL (3.0 mmol/L) is a severe
     * (level 2) hypo; any hypo scores at least 2 because it needs treating
     * straight away, whereas moderate highs build more gradually.
     */
    public static function scoreBloodGlucose(mixed $value): ?array
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return match (true) {
            $value < 54 => ['score' => 3, 'direction' => 'low'],
            $value < 70 => ['score' => 2, 'direction' => 'low'],
            $value <= 250 => ['score' => 0, 'direction' => 'normal'],
            $value <= 300 => ['score' => 1, 'direction' => 'high'],
            $value <= 400 => ['score' => 2, 'direction' => 'high'],
            default => ['score' => 3, 'direction' => 'high'],
        };
    }
}
