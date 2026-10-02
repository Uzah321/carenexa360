<?php

namespace App\Modules\Observations\Support;

/**
 * National Early Warning Score 2 (Royal College of Physicians, 2017).
 *
 * Scores each physiological parameter 0–3 against the RCP chart, and a full
 * set of observations into an aggregate score and clinical risk band. Every
 * band below is an upper bound checked in order, so decimal readings that
 * fall between the chart's printed integer ranges (e.g. a 35.05°C
 * temperature) still land in the right band.
 *
 * The web app mirrors this logic in web/src/modules/observations/news2.ts for
 * live feedback while recording — keep the two in step.
 */
class News2
{
    public const CONSCIOUSNESS_LEVELS = ['alert', 'new_confusion', 'voice', 'pain', 'unresponsive'];

    /** Parameters a full NEWS2 set must include. */
    public const FULL_SET_FIELDS = [
        'respiration_rate',
        'spo2',
        'on_oxygen',
        'systolic',
        'pulse',
        'consciousness',
        'temperature',
    ];

    /**
     * Each band is [upper bound inclusive, score, direction]. The last band
     * has no upper bound.
     */
    protected const BANDS = [
        'respiration_rate' => [[8, 3, 'low'], [11, 1, 'low'], [20, 0, 'normal'], [24, 2, 'high'], [null, 3, 'high']],
        'spo2_scale_1' => [[91, 3, 'low'], [93, 2, 'low'], [95, 1, 'low'], [null, 0, 'normal']],
        'systolic' => [[90, 3, 'low'], [100, 2, 'low'], [110, 1, 'low'], [219, 0, 'normal'], [null, 3, 'high']],
        'pulse' => [[40, 3, 'low'], [50, 1, 'low'], [90, 0, 'normal'], [110, 1, 'high'], [130, 2, 'high'], [null, 3, 'high']],
        'temperature' => [[35.0, 3, 'low'], [36.0, 1, 'low'], [38.0, 0, 'normal'], [39.0, 1, 'high'], [null, 2, 'high']],
    ];

    protected const LABELS = [
        'respiration_rate' => ['Respiration rate', 'breaths/min'],
        'spo2' => ['Oxygen saturation', '%'],
        'air_or_oxygen' => ['Supplemental oxygen', ''],
        'systolic' => ['Systolic blood pressure', 'mmHg'],
        'pulse' => ['Pulse', 'bpm'],
        'consciousness' => ['Consciousness', ''],
        'temperature' => ['Temperature', '°C'],
    ];

    /**
     * @return array{score: int, direction: string}|null
     */
    public static function scoreParameter(string $parameter, mixed $value): ?array
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        foreach (self::BANDS[$parameter] as [$upper, $score, $direction]) {
            if ($upper === null || $value <= $upper) {
                return ['score' => $score, 'direction' => $direction];
            }
        }

        return null;
    }

    /**
     * SpO2 Scale 2 is for people with a clinician-confirmed target range of
     * 88–92% (e.g. hypercapnic respiratory failure) — high saturations only
     * score when they're achieved on supplemental oxygen.
     *
     * @return array{score: int, direction: string}|null
     */
    public static function scoreSpo2(mixed $value, int $scale, bool $onOxygen): ?array
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        if ($scale !== 2) {
            return self::scoreParameter('spo2_scale_1', $value);
        }

        $value = (float) $value;

        return match (true) {
            $value <= 83 => ['score' => 3, 'direction' => 'low'],
            $value <= 85 => ['score' => 2, 'direction' => 'low'],
            $value <= 87 => ['score' => 1, 'direction' => 'low'],
            $value <= 92, ! $onOxygen => ['score' => 0, 'direction' => 'normal'],
            $value <= 94 => ['score' => 1, 'direction' => 'high'],
            $value <= 96 => ['score' => 2, 'direction' => 'high'],
            default => ['score' => 3, 'direction' => 'high'],
        };
    }

    public static function scoreConsciousness(mixed $level): ?array
    {
        if (! in_array($level, self::CONSCIOUSNESS_LEVELS, true)) {
            return null;
        }

        return $level === 'alert'
            ? ['score' => 0, 'direction' => 'normal']
            : ['score' => 3, 'direction' => 'abnormal'];
    }

    /**
     * Scores every NEWS2 parameter present in an observation. For a
     * single-parameter type only that parameter is returned; for a full
     * `news2` set all seven are.
     *
     * @return array<string, array{parameter: string, label: string, reading: string, score: int, direction: string}>
     */
    public static function parameterScores(string $type, array $value): array
    {
        $onOxygen = filter_var($value['on_oxygen'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $scale = (int) ($value['spo2_scale'] ?? 1);

        $results = match ($type) {
            'respiratory_rate' => ['respiration_rate' => [self::scoreParameter('respiration_rate', $value['value'] ?? null), $value['value'] ?? null]],
            'oxygen_saturation' => [
                'spo2' => [self::scoreSpo2($value['value'] ?? null, $scale, $onOxygen), $value['value'] ?? null],
                // Only scored when the carer said whether oxygen was in use —
                // older readings never captured it.
                'air_or_oxygen' => array_key_exists('on_oxygen', $value) ? [self::scoreOxygen($onOxygen), $onOxygen] : [null, null],
            ],
            'blood_pressure' => ['systolic' => [self::scoreParameter('systolic', $value['systolic'] ?? null), $value['systolic'] ?? null]],
            'pulse' => ['pulse' => [self::scoreParameter('pulse', $value['value'] ?? null), $value['value'] ?? null]],
            'temperature' => ['temperature' => [self::scoreParameter('temperature', $value['value'] ?? null), $value['value'] ?? null]],
            'news2' => [
                'respiration_rate' => [self::scoreParameter('respiration_rate', $value['respiration_rate'] ?? null), $value['respiration_rate'] ?? null],
                'spo2' => [self::scoreSpo2($value['spo2'] ?? null, $scale, $onOxygen), $value['spo2'] ?? null],
                'air_or_oxygen' => [self::scoreOxygen($onOxygen), $onOxygen],
                'systolic' => [self::scoreParameter('systolic', $value['systolic'] ?? null), $value['systolic'] ?? null],
                'pulse' => [self::scoreParameter('pulse', $value['pulse'] ?? null), $value['pulse'] ?? null],
                'consciousness' => [self::scoreConsciousness($value['consciousness'] ?? null), $value['consciousness'] ?? null],
                'temperature' => [self::scoreParameter('temperature', $value['temperature'] ?? null), $value['temperature'] ?? null],
            ],
            default => [],
        };

        $scores = [];
        foreach ($results as $parameter => [$result, $reading]) {
            if ($result === null) {
                continue;
            }
            [$label, $unit] = self::LABELS[$parameter];
            $scores[$parameter] = [
                'parameter' => $parameter,
                'label' => $label,
                'reading' => self::formatReading($parameter, $reading, $unit, $scale),
                ...$result,
            ];
        }

        return $scores;
    }

    /**
     * The NEWS2 assessment for an observation, or null when its type isn't
     * one NEWS2 scores (weight, glucose, mood…).
     *
     * @return array{total: int, risk: string, single_parameter_3: bool, response: string, parameters: list<array>}|null
     */
    public static function assess(string $type, array $value): ?array
    {
        $parameters = self::parameterScores($type, $value);

        if ($parameters === []) {
            return null;
        }

        $total = array_sum(array_column($parameters, 'score'));
        $singleThree = in_array(3, array_column($parameters, 'score'), true);
        $risk = self::clinicalRisk($total, $singleThree);

        return [
            'total' => $total,
            'risk' => $risk,
            'single_parameter_3' => $singleThree,
            'response' => self::RESPONSES[$risk],
            'parameters' => array_values($parameters),
        ];
    }

    public static function clinicalRisk(int $total, bool $singleParameterThree): string
    {
        return match (true) {
            $total >= 7 => 'high',
            $total >= 5 => 'medium',
            $singleParameterThree => 'low_medium',
            $total >= 1 => 'low',
            default => 'none',
        };
    }

    /**
     * RCP NEWS2 clinical response thresholds, phrased for a community / home
     * care setting. Providers should follow their own escalation policy.
     */
    public const RESPONSES = [
        'none' => 'No concerns — continue routine monitoring.',
        'low' => 'Low risk — inform the senior carer / nurse, who should decide whether monitoring needs to increase.',
        'low_medium' => 'Low-medium risk — a single parameter is in the red zone. Seek urgent clinical advice (GP or NHS 111).',
        'medium' => 'Medium risk — urgent clinical review needed. Contact the GP / NHS 111 urgently and monitor at least hourly.',
        'high' => 'High risk — emergency response. Call 999.',
    ];

    protected static function scoreOxygen(bool $onOxygen): array
    {
        return $onOxygen ? ['score' => 2, 'direction' => 'abnormal'] : ['score' => 0, 'direction' => 'normal'];
    }

    protected static function formatReading(string $parameter, mixed $reading, string $unit, int $scale): string
    {
        return match ($parameter) {
            'air_or_oxygen' => $reading ? 'On oxygen' : 'Air',
            'consciousness' => ucfirst(str_replace('_', ' ', (string) $reading)),
            'spo2' => "{$reading}% (Scale {$scale})",
            default => trim("{$reading} {$unit}"),
        };
    }
}
