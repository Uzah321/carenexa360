<?php

namespace App\Modules\Tracking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tracking\Http\Requests\CheckOutDutyPeriodRequest;
use App\Modules\Tracking\Http\Requests\ForceCloseDutyPeriodRequest;
use App\Modules\Tracking\Http\Requests\StoreDutyPeriodRequest;
use App\Modules\Tracking\Http\Resources\DutyPeriodResource;
use App\Modules\Tracking\Models\DutyPeriod;
use App\Modules\Tracking\Support\TrackingRoles;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DutyPeriodController extends Controller
{
    /**
     * The caller's own open (not yet checked out) duty period, if any — the
     * "am I currently on duty" check My Day starts from on every load.
     */
    public function current(Request $request)
    {
        $period = DutyPeriod::where('user_id', $request->user()->id)
            ->whereNull('ended_at')
            ->latest('started_at')
            ->first();

        return $period ? new DutyPeriodResource($period) : response()->json(['data' => null]);
    }

    public function store(StoreDutyPeriodRequest $request)
    {
        $alreadyOnDuty = DutyPeriod::where('user_id', $request->user()->id)
            ->whereNull('ended_at')
            ->exists();

        if ($alreadyOnDuty) {
            throw ValidationException::withMessages([
                'started_at' => ['You are already checked in for work.'],
            ]);
        }

        $period = DutyPeriod::create([
            'tenant_id' => $request->user()->tenant_id,
            'user_id' => $request->user()->id,
            'started_at' => now(),
            'start_lat' => $request->validated('latitude'),
            'start_lng' => $request->validated('longitude'),
            'start_accuracy' => $request->validated('accuracy'),
        ]);

        return (new DutyPeriodResource($period))->response()->setStatusCode(201);
    }

    /**
     * Shifts still open — what a manager needs to see to chase or close a
     * forgotten check-out. Ordered oldest first, since the longest-running one
     * is the most likely to be a mistake.
     */
    public function open(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(TrackingRoles::ALLOWED), 403);

        return DutyPeriodResource::collection(
            DutyPeriod::whereNull('ended_at')
                ->with('carer')
                ->orderBy('started_at')
                ->get()
        );
    }

    /**
     * A manager ending a shift the carer never checked out of. The reason is
     * required and stored with who closed it, so the record still explains
     * itself later — there is no silent close.
     */
    public function forceClose(ForceCloseDutyPeriodRequest $request, DutyPeriod $dutyPeriod)
    {
        abort_unless($request->user()->hasAnyRole(TrackingRoles::ALLOWED), 403);

        if ($dutyPeriod->ended_at) {
            throw ValidationException::withMessages([
                'ended_at' => ['This duty period has already ended.'],
            ]);
        }

        $dutyPeriod->update([
            'ended_at' => now(),
            'close_reason' => $request->validated('reason'),
            'closed_by' => $request->user()->id,
        ]);

        return new DutyPeriodResource($dutyPeriod->fresh()->load('closedBy'));
    }

    public function checkOut(CheckOutDutyPeriodRequest $request, DutyPeriod $dutyPeriod)
    {
        abort_unless($dutyPeriod->user_id === $request->user()->id, 403);

        if ($dutyPeriod->ended_at) {
            throw ValidationException::withMessages([
                'ended_at' => ['This duty period has already ended.'],
            ]);
        }

        $dutyPeriod->update([
            'ended_at' => now(),
            'end_lat' => $request->validated('latitude'),
            'end_lng' => $request->validated('longitude'),
            'end_accuracy' => $request->validated('accuracy'),
        ]);

        return new DutyPeriodResource($dutyPeriod->fresh());
    }
}
