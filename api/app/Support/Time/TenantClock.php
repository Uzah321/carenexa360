<?php

namespace App\Support\Time;

use App\Modules\Organization\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Answers "what day is it?" from the tenant's point of view.
 *
 * Timestamps are stored in UTC and should stay that way — but "today" is a human
 * question, not a UTC one. A carer in Harare (UTC+2) opening the app at 01:00 is
 * on 4 September while UTC is still on the 3rd, so a server-derived `now()->toDateString()`
 * hands them the previous day's rota under today's heading. Every tenant is
 * already configured with its own timezone (Harare, Johannesburg, Lusaka), so
 * server-side "today" should come from here rather than from UTC.
 */
class TenantClock
{
    /** The tenant's configured timezone, falling back to the app default. */
    public static function timezoneFor(?int $tenantId): string
    {
        $timezone = $tenantId ? Tenant::find($tenantId)?->timezone : null;

        return $timezone && in_array($timezone, timezone_identifiers_list(), true) ? $timezone : (string) config('app.timezone');
    }

    /** The calendar date it currently is for this tenant, as YYYY-MM-DD. */
    public static function today(?int $tenantId): string
    {
        return Carbon::now(self::timezoneFor($tenantId))->toDateString();
    }

    /**
     * A tenant-local wall-clock time ("2026-06-10" + "09:00", as visits,
     * shifts and dose times are stored) as the real instant it refers to.
     * Comparing it with a UTC timestamp (a check-in, a dose given) is then
     * correct whatever the tenant's offset.
     */
    public static function wallClock(?int $tenantId, string $date, string $time): Carbon
    {
        return Carbon::parse("{$date} {$time}", self::timezoneFor($tenantId));
    }

    /** A stored UTC timestamp as the tenant's local time, for display or time-of-day comparisons. */
    public static function local(?int $tenantId, \DateTimeInterface $instant): Carbon
    {
        return Carbon::instance($instant)->setTimezone(self::timezoneFor($tenantId));
    }

    /** The current moment in the tenant's timezone. */
    public static function now(?int $tenantId): Carbon
    {
        return Carbon::now(self::timezoneFor($tenantId));
    }

    /**
     * The UTC instants bounding a tenant-local calendar day.
     *
     * Needed whenever a *timestamp* column is filtered by day: `whereDate()` on a
     * UTC-stored timestamp compares UTC calendar dates, so a check-in at 01:00
     * Harare (23:00 UTC the day before) would fall outside its own local day.
     * Comparing against this range instead keeps the boundary where the tenant
     * expects it.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function dayBoundsUtc(?int $tenantId, ?string $date = null): array
    {
        $timezone = self::timezoneFor($tenantId);
        $day = Carbon::parse($date ?: self::today($tenantId), $timezone);

        return [
            $day->copy()->startOfDay()->utc(),
            $day->copy()->endOfDay()->utc(),
        ];
    }
}
