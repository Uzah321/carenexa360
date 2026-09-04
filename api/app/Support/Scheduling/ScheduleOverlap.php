<?php

namespace App\Support\Scheduling;

use App\Modules\Rostering\Models\Shift;
use App\Modules\Visits\Models\Visit;

class ScheduleOverlap
{
    /**
     * Whether the given user already has a visit or shift overlapping this
     * date/time window, for the given tenant. A carer can't be in two
     * places at once, so this is used as a hard validation rule.
     *
     * @param  array{visit?: int, shift?: int}  $excludeIds  Record ids to ignore (when updating that same record).
     */
    public static function conflictExists(
        int $tenantId,
        int $userId,
        string $date,
        string $startTime,
        string $endTime,
        array $excludeIds = [],
    ): bool {
        $visitConflict = Visit::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('carer_id', $userId)
            ->where('visit_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->when(isset($excludeIds['visit']), fn ($q) => $q->where('id', '!=', $excludeIds['visit']))
            ->exists();

        if ($visitConflict) {
            return true;
        }

        return Shift::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('shift_date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->when(isset($excludeIds['shift']), fn ($q) => $q->where('id', '!=', $excludeIds['shift']))
            ->exists();
    }
}
