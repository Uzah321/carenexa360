<?php

namespace App\Modules\Rostering\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Rostering\Http\Requests\StoreShiftRequest;
use App\Modules\Rostering\Http\Requests\UpdateShiftRequest;
use App\Modules\Rostering\Http\Resources\ShiftResource;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Rostering\Support\RosteringRoles;
use App\Support\Scheduling\ScheduleOverlap;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ShiftController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(RosteringRoles::ALLOWED), 403);

        $shifts = Shift::with('user')
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->when($request->query('date'), fn ($q, $date) => $q->where('shift_date', $date))
            ->orderBy('shift_date')
            ->orderBy('start_time')
            ->paginate(20);

        return ShiftResource::collection($shifts);
    }

    public function store(StoreShiftRequest $request)
    {
        abort_unless($request->user()->hasAnyRole(RosteringRoles::ALLOWED), 403);

        $tenantId = $request->user()->tenant_id;

        $this->assertNoConflict(
            $tenantId,
            $request->validated('user_id'),
            $request->validated('shift_date'),
            $request->validated('start_time'),
            $request->validated('end_time'),
        );

        $shift = Shift::create([
            ...$request->validated(),
            'tenant_id' => $tenantId,
        ]);

        return (new ShiftResource($shift->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Shift $shift)
    {
        abort_unless($request->user()->hasAnyRole(RosteringRoles::ALLOWED), 403);
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $shift->tenant_id,
            403
        );

        return new ShiftResource($shift->load('user'));
    }

    public function update(UpdateShiftRequest $request, Shift $shift)
    {
        abort_unless($request->user()->hasAnyRole(RosteringRoles::ALLOWED), 403);
        abort_unless($request->user()->ownsTenant($shift->tenant_id), 403);

        $this->assertNoConflict(
            $shift->tenant_id,
            $shift->user_id,
            $request->validated('shift_date', $shift->shift_date->toDateString()),
            $request->validated('start_time', $shift->start_time),
            $request->validated('end_time', $shift->end_time),
            ['shift' => $shift->id],
        );

        $shift->update($request->validated());

        return new ShiftResource($shift->fresh()->load('user'));
    }

    protected function assertNoConflict(
        int $tenantId,
        int $userId,
        string $date,
        string $startTime,
        string $endTime,
        array $excludeIds = [],
    ): void {
        if (ScheduleOverlap::conflictExists($tenantId, $userId, $date, $startTime, $endTime, $excludeIds)) {
            throw ValidationException::withMessages([
                'user_id' => ["This staff member already has an overlapping visit or shift on {$date}."],
            ]);
        }
    }
}
