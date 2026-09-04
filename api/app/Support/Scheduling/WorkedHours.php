<?php

namespace App\Support\Scheduling;

use App\Modules\Rostering\Models\Shift;
use App\Modules\Visits\Models\Visit;
use Carbon\Carbon;

class WorkedHours
{
    /**
     * A completed visit's actual worked duration when both check-in and
     * check-out timestamps were captured, otherwise its scheduled duration.
     * Used by both Billing (invoice line items) and Payroll (payslip
     * generation) so the two never disagree on how a visit's hours are
     * computed.
     */
    public static function forVisit(Visit $visit): float
    {
        if ($visit->check_in_at && $visit->check_out_at) {
            return round($visit->check_in_at->floatDiffInMinutes($visit->check_out_at) / 60, 2);
        }

        return self::scheduledHours($visit->start_time, $visit->end_time);
    }

    /**
     * A shift's scheduled duration — Shift has no check-in/check-out
     * concept, so this is always the scheduled window.
     */
    public static function forShift(Shift $shift): float
    {
        return self::scheduledHours($shift->start_time, $shift->end_time);
    }

    protected static function scheduledHours(string $startTime, string $endTime): float
    {
        $start = Carbon::parse($startTime);
        $end = Carbon::parse($endTime);

        return round($start->floatDiffInMinutes($end) / 60, 2);
    }
}
