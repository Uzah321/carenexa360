<?php

namespace App\Modules\Observations\Support;

class ObservationThresholds
{
    /**
     * Decides whether a reading needs a clinical alert. Every scored
     * measurement gets a 0–3 score: the vitals NEWS2 covers (respiration,
     * SpO2, systolic BP, pulse, temperature, and a full NEWS2 set) by News2,
     * and diastolic BP and blood glucose by RangeScores. Any non-zero score
     * is outside the normal range; a 3 (red zone) is critical on its own.
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

        $abnormal = [
            ...array_map(fn ($p) => [...$p, 'scale' => 'NEWS2 score'], array_values(News2::parameterScores($type, $value))),
            ...array_map(fn ($p) => [...$p, 'scale' => 'score'], array_values(RangeScores::parameterScores($type, $value))),
        ];
        $abnormal = array_filter($abnormal, fn ($p) => $p['score'] > 0);

        if ($abnormal === []) {
            return null;
        }

        return [
            'message' => implode('; ', array_map(fn ($p) => self::describe($p, $p['scale']), $abnormal)),
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
                .implode('; ', array_map(fn ($p) => self::describe($p, 'NEWS2 score'), $abnormal))
                .'. '.$assessment['response'],
            'severity' => in_array($assessment['risk'], ['medium', 'high'], true) ? 'critical' : 'warning',
        ];
    }

    protected static function describe(array $parameter, string $scale): string
    {
        $prefix = match ($parameter['direction']) {
            'low' => 'Low ',
            'high' => 'High ',
            default => '',
        };
        $label = $prefix === '' ? $parameter['label'] : lcfirst($parameter['label']);

        return "{$prefix}{$label} (reading: {$parameter['reading']}, {$scale} {$parameter['score']})";
    }
}
