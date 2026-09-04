<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tracking\Http\Requests\StoreCarerLocationRequest;
use App\Modules\Tracking\Models\CarerLocation;
use App\Modules\Tracking\Models\DutyPeriod;
use App\Modules\Tracking\Support\TrackingRoles;
use App\Modules\Visits\Models\Visit;
use App\Support\Time\TenantClock;
use Illuminate\Http\Request;

class CarerLocationController extends Controller
{
    public function store(StoreCarerLocationRequest $request)
    {
        $location = CarerLocation::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'recorded_at' => now(),
        ]);

        return response()->json(['data' => ['id' => $location->id]], 201);
    }

    /**
     * "Checked in" here means on duty (has an open DutyPeriod today) — not
     * merely mid-visit — so a carer travelling between clients still shows
     * as trackable, matching what the map itself already plots (it draws a
     * trail from every location ping today, visit or no visit).
     */
    public function live(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(TrackingRoles::ALLOWED), 403);

        // "Today" is the tenant's calendar day, and started_at/recorded_at are
        // stored in UTC — so filter on the UTC instants bounding that local day
        // rather than on a UTC calendar date, which would drop a carer who checked
        // in after local midnight but before UTC rolled over.
        $tenantId = $request->user()->tenant_id;
        $today = TenantClock::today($tenantId);
        [$dayStart, $dayEnd] = TenantClock::dayBoundsUtc($tenantId, $today);
        $branchId = $request->query('branch_id');

        $dutyPeriods = DutyPeriod::whereBetween('started_at', [$dayStart, $dayEnd])
            ->with('carer.staffProfile')
            ->get()
            ->filter(fn (DutyPeriod $period) => ! $branchId || $period->carer?->staffProfile?->branch_id == $branchId)
            ->filter(fn (DutyPeriod $period) => (bool) $period->carer)
            ->groupBy('user_id')
            ->map(fn ($periods) => $periods->sortByDesc('started_at')->first());

        $carerIds = $dutyPeriods->keys();

        $inProgressVisits = Visit::whereDate('visit_date', $today)
            ->whereIn('carer_id', $carerIds)
            ->where('status', 'in_progress')
            ->with('serviceUser')
            ->get()
            ->keyBy('carer_id');

        $carerLocations = CarerLocation::whereBetween('recorded_at', [$dayStart, $dayEnd])
            ->whereIn('user_id', $carerIds)
            ->orderBy('recorded_at')
            ->get()
            ->groupBy('user_id');

        $checkedIn = [];
        $checkedOut = [];
        $carers = [];

        foreach ($dutyPeriods as $carerId => $period) {
            $carer = $period->carer;
            $onDuty = is_null($period->ended_at);
            $currentVisit = $inProgressVisits->get($carerId);

            $trail = ($carerLocations->get($carerId) ?? collect())
                ->map(fn (CarerLocation $ping) => [
                    'latitude' => (float) $ping->latitude,
                    'longitude' => (float) $ping->longitude,
                    'recorded_at' => $ping->recorded_at,
                ])
                ->values();

            if ($onDuty) {
                $checkedIn[] = [
                    'user_id' => $carer->id,
                    'name' => $carer->name,
                    'service_user_name' => $currentVisit
                        ? trim("{$currentVisit->serviceUser?->first_name} {$currentVisit->serviceUser?->last_name}")
                        : null,
                    'checked_in_at' => $period->started_at,
                ];
            } else {
                $checkedOut[] = [
                    'user_id' => $carer->id,
                    'name' => $carer->name,
                    'service_user_name' => null,
                    'checked_out_at' => $period->ended_at,
                ];
            }

            $carers[] = [
                'user_id' => $carer->id,
                'name' => $carer->name,
                'is_checked_in' => $onDuty,
                'last_ping_at' => $trail->last()['recorded_at'] ?? null,
                'trail' => $trail,
            ];
        }

        return response()->json([
            'date' => $today,
            'checked_in' => ['count' => count($checkedIn), 'items' => $checkedIn],
            'checked_out' => ['count' => count($checkedOut), 'items' => $checkedOut],
            'carers' => $carers,
        ]);
    }
}
