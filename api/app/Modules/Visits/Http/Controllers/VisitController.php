<?php

namespace App\Modules\Visits\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Visits\Http\Requests\StoreVisitRequest;
use App\Modules\Visits\Http\Requests\UpdateVisitRequest;
use App\Modules\Visits\Http\Resources\VisitResource;
use App\Modules\Visits\Models\Visit;
use App\Modules\Visits\Support\SchedulingRoles;
use App\Support\Scheduling\ScheduleOverlap;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitController extends Controller
{
    public function index(Request $request)
    {
        $visits = Visit::with(['serviceUser', 'carer'])
            ->when($request->query('carer_id'), fn ($q, $carerId) => $q->where('carer_id', $carerId))
            ->when($request->query('service_user_id'), fn ($q, $id) => $q->where('service_user_id', $id))
            ->when($request->query('date'), fn ($q, $date) => $q->where('visit_date', $date))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('visit_date')
            ->orderBy('start_time')
            ->paginate(max(1, min((int) $request->query('per_page', 20), 100)));

        return VisitResource::collection($visits);
    }

    public function store(StoreVisitRequest $request)
    {
        $tenantId = $request->user()->tenant_id;
        $carerId = $request->validated('carer_id');
        $recurrence = $request->validated('recurrence');

        $dates = $recurrence
            ? $this->expandRecurrence($request->validated('visit_date'), $recurrence)
            : [$request->validated('visit_date')];

        if ($carerId) {
            foreach ($dates as $date) {
                if (ScheduleOverlap::conflictExists(
                    $tenantId,
                    $carerId,
                    $date,
                    $request->validated('start_time'),
                    $request->validated('end_time'),
                )) {
                    throw ValidationException::withMessages([
                        'carer_id' => ["This carer already has an overlapping visit or shift on {$date}."],
                    ]);
                }
            }
        }

        $warnings = $this->skillWarnings($carerId, $request->validated('required_skills', []));

        $visits = DB::transaction(function () use ($request, $tenantId, $dates) {
            $attributes = $request->safe()->except(['recurrence', 'visit_date']);

            $created = collect($dates)->map(fn ($date) => Visit::create([
                ...$attributes,
                'tenant_id' => $tenantId,
                'visit_date' => $date,
                'status' => 'scheduled',
            ]));

            return EloquentCollection::make($created);
        });

        $visits->load(['serviceUser', 'carer']);

        return response()->json([
            'data' => $visits->count() === 1
                ? (new VisitResource($visits->first()))->resolve($request)
                : $visits->map(fn ($visit) => (new VisitResource($visit))->resolve($request))->all(),
            'warnings' => $warnings,
        ], 201);
    }

    public function show(Request $request, Visit $visit)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $visit->tenant_id,
            403
        );

        return new VisitResource($visit->load(['serviceUser', 'carer']));
    }

    public function update(UpdateVisitRequest $request, Visit $visit)
    {
        $tenantId = $visit->tenant_id;
        $carerId = $request->validated('carer_id', $visit->carer_id);
        $date = $request->validated('visit_date', $visit->visit_date->toDateString());
        $startTime = $request->validated('start_time', $visit->start_time);
        $endTime = $request->validated('end_time', $visit->end_time);

        $changesSchedule = $request->hasAny(['carer_id', 'visit_date', 'start_time', 'end_time']);

        // Carry a reason: without one the client only has a bare 403 to show, and
        // "Something went wrong" gives a carer no idea why their edit was refused.
        abort_if(
            $changesSchedule && ! $request->user()->hasAnyRole(SchedulingRoles::ALLOWED),
            403,
            'You do not have permission to reschedule or reassign visits.'
        );

        // The task checklist is a record of care actually delivered, so it may
        // only be filled in while the visit is running. Checking out completes
        // the visit and freezes it — the UI disables the checkboxes at that
        // point, but the rule has to live here too, or a direct API call could
        // still rewrite a finished visit's care record after the fact.
        if ($request->hasAny(['completed_care_tasks', 'medication_tasks_completed'])
            && $visit->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'status' => ["The task checklist can only be changed while the visit is in progress (this visit is {$visit->status})."],
            ]);
        }

        if ($changesSchedule && $carerId && ScheduleOverlap::conflictExists(
            $tenantId, $carerId, $date, $startTime, $endTime, ['visit' => $visit->id]
        )) {
            throw ValidationException::withMessages([
                'carer_id' => ["This carer already has an overlapping visit or shift on {$date}."],
            ]);
        }

        $warnings = $this->skillWarnings($carerId, $request->validated('required_skills', $visit->required_skills ?? []));

        $visit->update($request->validated());

        return response()->json([
            'data' => (new VisitResource($visit->fresh()->load(['serviceUser', 'carer'])))->resolve($request),
            'warnings' => $warnings,
        ]);
    }

    protected function expandRecurrence(string $startDate, array $recurrence): array
    {
        $weekdays = $recurrence['weekdays'];
        $period = CarbonPeriod::create($startDate, $recurrence['until']);

        return collect($period)
            ->filter(fn ($date) => in_array((int) $date->dayOfWeek, $weekdays, true))
            ->map(fn ($date) => $date->toDateString())
            ->values()
            ->all();
    }

    protected function skillWarnings(?int $carerId, array $requiredSkills): array
    {
        if (! $carerId || empty($requiredSkills)) {
            return [];
        }

        // Fetch the hydrated model (not ->value()) so the 'skills' jsonb
        // column passes through Eloquent's array cast instead of coming
        // back as a raw JSON string.
        $carerSkills = StaffProfile::withoutTenantScope()
            ->where('user_id', $carerId)
            ->first()?->skills ?? [];

        $missing = array_diff($requiredSkills, $carerSkills);

        if (empty($missing)) {
            return [];
        }

        return ['Assigned carer is missing required skill(s): '.implode(', ', $missing)];
    }
}
