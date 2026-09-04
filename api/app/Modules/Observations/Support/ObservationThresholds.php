<?php

namespace App\Modules\Observations\Support;

class ObservationThresholds
{
    /**
     * Sensible hardcoded clinical default thresholds for the vitals where an
     * out-of-range reading is a genuine safety concern. Per-tenant configurable
     * thresholds are a deferred settings/admin concern — this is the load-bearing
     * safety behaviour: an out-of-range reading should never silently pass by
     * unnoticed, regardless of whether a tenant has customized it yet.
     *
     * @return array{message: string, severity: string}|null
     */
    public static function check(string $type, array $value): ?array
    {
        return match ($type) {
            'oxygen_saturation' => self::checkRange($value['value'] ?? null, 92, null, 'Low oxygen saturation', '%', 'critical'),
            'pulse' => self::checkRange($value['value'] ?? null, 50, 120, 'Pulse out of normal range (50-120 bpm)', '', 'warning'),
            'temperature' => self::checkRange($value['value'] ?? null, 35, 38, 'Temperature out of normal range (35-38°C)', '', 'warning'),
            'blood_glucose' => self::checkRange($value['value'] ?? null, 70, 250, 'Blood glucose out of normal range (70-250 mg/dL)', '', 'warning'),
            'respiratory_rate' => self::checkRange($value['value'] ?? null, 12, 20, 'Respiratory rate out of normal range (12-20 breaths/min)', '', 'warning'),
            'blood_pressure' => self::checkBloodPressure($value),
            default => null,
        };
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

    protected static function checkBloodPressure(array $value): ?array
    {
        $systolic = $value['systolic'] ?? null;
        $diastolic = $value['diastolic'] ?? null;

        if (! is_numeric($systolic) || ! is_numeric($diastolic)) {
            return null;
        }

        if ($systolic < 90 || $systolic > 180 || $diastolic < 60 || $diastolic > 120) {
            return [
                'message' => "Blood pressure out of normal range (reading: {$systolic}/{$diastolic} mmHg)",
                'severity' => ($systolic > 180 || $diastolic > 120) ? 'critical' : 'warning',
            ];
        }

        return null;
    }
}
