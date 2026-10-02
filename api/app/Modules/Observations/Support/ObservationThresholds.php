<?php

namespace App\Modules\Observations\Support;

class ObservationThresholds
{
    /**
     * Decides whether a reading needs a clinical alert. The vitals NEWS2
     * covers (respiration, SpO2, systolic BP, pulse, temperature, and a full
     * NEWS2 set) are judged by their NEWS2 score — see News2. Glucose and
     * diastolic BP aren't part of NEWS2, so they keep fixed default ranges.
     *
     * Per-tenant configurable thresholds are a deferred settings/admin
     * concern — this is the load-bearing safety behaviour: an out-of-range
     * reading should never silently pass by unnoticed, regardless of whether
     * a tenant has customized it yet.
     *
     * @return array{message: string, severity: string}|null
     */
    public static function check(string $type, array $value): ?array
    {
        if ($type === 'news2') {
            return self::checkNews2Set($value);
        }

        $breaches = array_filter([
            self::checkNews2Parameters($type, $value),
            match ($type) {
                'blood_glucose' => self::checkRange($value['value'] ?? null, 70, 250, 'Blood glucose out of normal range (70-250 mg/dL)', '', 'warning'),
                'blood_pressure' => self::checkDiastolic($value),
                default => null,
            },
        ]);

        if ($breaches === []) {
            return null;
        }

        return [
            'message' => implode('; ', array_column($breaches, 'message')),
            'severity' => in_array('critical', array_column($breaches, 'severity'), true) ? 'critical' : 'warning',
        ];
    }

    /**
     * Any non-zero NEWS2 parameter score is outside the optimal range — a
     * score of 3 (the chart's red zone) is critical on its own.
     */
    protected static function checkNews2Parameters(string $type, array $value): ?array
    {
        $abnormal = array_filter(News2::parameterScores($type, $value), fn ($p) => $p['score'] > 0);

        if ($abnormal === []) {
            return null;
        }

        $messages = array_map(fn ($p) => self::describe($p), $abnormal);

        return [
            'message' => implode('; ', $messages),
            'severity' => max(array_column($abnormal, 'score')) >= 3 ? 'critical' : 'warning',
        ];
    }

    protected static function checkNews2Set(array $value): ?array
    {
        $assessment = News2::assess('news2', $value);

        if (! $assessment || $assessment['risk'] === 'none') {
            return null;
        }

        $abnormal = array_filter($assessment['parameters'], fn ($p) => $p['score'] > 0);
        $riskLabel = str_replace('_', '-', $assessment['risk']);

        return [
            'message' => "NEWS2 score {$assessment['total']} ({$riskLabel} risk): "
                .implode('; ', array_map(fn ($p) => self::describe($p), $abnormal))
                .'. '.$assessment['response'],
            'severity' => in_array($assessment['risk'], ['medium', 'high'], true) ? 'critical' : 'warning',
        ];
    }

    protected static function describe(array $parameter): string
    {
        $prefix = match ($parameter['direction']) {
            'low' => 'Low ',
            'high' => 'High ',
            default => '',
        };
        $label = $prefix === '' ? $parameter['label'] : lcfirst($parameter['label']);

        return "{$prefix}{$label} (reading: {$parameter['reading']}, NEWS2 score {$parameter['score']})";
    }

    protected static function checkRange(
        mixed $value,
        ?float $min,
        ?float $max,
        string $message,
        string $unit,
        string $severity,
    ): ?array {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        if (($min !== null && $value < $min) || ($max !== null && $value > $max)) {
            return ['message' => "{$message} (reading: {$value}{$unit})", 'severity' => $severity];
        }

        return null;
    }

    protected static function checkDiastolic(array $value): ?array
    {
        $diastolic = $value['diastolic'] ?? null;

        if (! is_numeric($diastolic)) {
            return null;
        }

        if ($diastolic > 120) {
            return ['message' => "High diastolic blood pressure (reading: {$diastolic} mmHg)", 'severity' => 'critical'];
        }

        if ($diastolic < 60) {
            return ['message' => "Low diastolic blood pressure (reading: {$diastolic} mmHg)", 'severity' => 'warning'];
        }

        return null;
    }
}
