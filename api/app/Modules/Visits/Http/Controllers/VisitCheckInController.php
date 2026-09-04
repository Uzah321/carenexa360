<?php

namespace App\Modules\Visits\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Visits\Http\Requests\CheckInRequest;
use App\Modules\Visits\Http\Resources\VisitResource;
use App\Modules\Tracking\Models\DutyPeriod;
use App\Modules\Visits\Models\Visit;
use App\Modules\Visits\Support\SchedulingRoles;
use App\Support\Geo\Haversine;
use Illuminate\Validation\ValidationException;

class VisitCheckInController extends Controller
{
    protected const DEFAULT_GEOFENCE_RADIUS_METERS = 100;

    public function checkIn(CheckInRequest $request, Visit $visit)
    {
        $this->assertOnDuty($request);
        $this->assertWithinGeofence($request, $visit);

        $visit->update([
            'check_in_lat' => $request->validated('latitude'),
            'check_in_lng' => $request->validated('longitude'),
            'check_in_accuracy' => $request->validated('accuracy'),
            'check_in_at' => now(),
            'status' => 'in_progress',
            'override_reason' => $request->validated('override_reason'),
            'overridden_by' => $request->validated('override_reason') ? $request->user()->id : null,
        ]);

        return new VisitResource($visit->fresh()->load(['serviceUser', 'carer']));
    }

    public function checkOut(CheckInRequest $request, Visit $visit)
    {
        $this->assertWithinGeofence($request, $visit);

        $visit->update([
            'check_out_lat' => $request->validated('latitude'),
            'check_out_lng' => $request->validated('longitude'),
            'check_out_accuracy' => $request->validated('accuracy'),
            'check_out_at' => now(),
            'status' => 'completed',
        ]);

        return new VisitResource($visit->fresh()->load(['serviceUser', 'carer']));
    }

    /**
     * Starting a visit requires being checked in for work.
     *
     * Checking in for the day is what puts a carer on the Live Map and opens
     * their shift record, so a visit that begins before it leaves the shift
     * unaccounted for. Scheduling roles are exempt: when a coordinator starts a
     * visit it is an administrative action on someone else's behalf, not them
     * going out on the round.
     *
     * Note this guards check-IN only. Check-out stays open regardless, or a
     * shift that ended mid-visit would strand the visit in progress forever.
     */
    protected function assertOnDuty(CheckInRequest $request): void
    {
        $user = $request->user();

        if ($user->hasAnyRole(SchedulingRoles::ALLOWED) || DutyPeriod::isOnDuty($user->id)) {
            return;
        }

        throw ValidationException::withMessages([
            'duty_period' => ['Check in for work on My Day before starting a visit.'],
        ]);
    }

    protected function assertWithinGeofence(CheckInRequest $request, Visit $visit): void
    {
        $serviceUser = $visit->serviceUser()->withoutTenantScope()->first();

        if (! $serviceUser?->latitude || ! $serviceUser?->longitude) {
            // No known location for this service user — nothing to verify against.
            return;
        }

        $distance = Haversine::distanceInMeters(
            (float) $serviceUser->latitude,
            (float) $serviceUser->longitude,
            (float) $request->validated('latitude'),
            (float) $request->validated('longitude'),
        );

        $radius = $this->geofenceRadiusFor($visit);

        if ($distance <= $radius) {
            return;
        }

        if ($request->validated('override_reason')) {
            return;
        }

        throw ValidationException::withMessages([
            'latitude' => [sprintf(
                'You are %dm away from the service user\'s address (must be within %dm). Provide an override reason to proceed anyway.',
                round($distance),
                $radius,
            )],
        ]);
    }

    protected function geofenceRadiusFor(Visit $visit): int
    {
        $tenant = Tenant::find($visit->tenant_id);

        return (int) ($tenant?->setting('geofence_radius_meters') ?? self::DEFAULT_GEOFENCE_RADIUS_METERS);
    }
}
