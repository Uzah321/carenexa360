<?php

namespace App\Modules\Observations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Observations\Http\Requests\StoreObservationRequest;
use App\Modules\Observations\Http\Requests\UpdateObservationRequest;
use App\Modules\Observations\Http\Resources\ObservationResource;
use App\Modules\Observations\Models\ClinicalAlert;
use App\Modules\Observations\Models\Observation;
use App\Modules\Observations\Support\ObservationThresholds;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Http\Request;

class ObservationController extends Controller
{
    public function index(Request $request, ServiceUser $serviceUser)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $serviceUser->tenant_id,
            403
        );

        return ObservationResource::collection(
            $serviceUser->observations()
                ->with(['recordedBy', 'alerts'])
                ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
                ->orderByDesc('recorded_at')
                ->get()
        );
    }

    public function store(StoreObservationRequest $request, ServiceUser $serviceUser)
    {
        abort_unless($request->user()->ownsTenant($serviceUser->tenant_id), 403);

        $observation = $serviceUser->observations()->create([
            ...$request->validated(),
            // validated() keeps only the `value.*` keys that have their own
            // rule (the NEWS2 ones) — the shape varies by type, so take the
            // whole array, which is itself validated as an array.
            'value' => $request->input('value'),
            'tenant_id' => $serviceUser->tenant_id,
            'recorded_by' => $request->user()->id,
            'recorded_at' => $request->validated('recorded_at') ?? now(),
        ]);

        $this->raiseAlertIfOutOfRange($observation);

        return (new ObservationResource($observation->load(['recordedBy', 'alerts'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Observation $observation)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $observation->tenant_id,
            403
        );

        return new ObservationResource($observation->load(['recordedBy', 'alerts']));
    }

    public function update(UpdateObservationRequest $request, Observation $observation)
    {
        abort_unless($request->user()->ownsTenant($observation->tenant_id), 403);

        $observation->update([
            ...$request->validated(),
            // See store() for why `value` comes from input().
            ...($request->has('value') ? ['value' => $request->input('value')] : []),
        ]);

        // A corrected reading must be re-judged: drop the alert the old value
        // raised (unless someone already acknowledged it — that stays as the
        // record of what was seen) and check the new value.
        if ($observation->wasChanged('value')) {
            $observation->alerts()->whereNull('acknowledged_at')->delete();
            $this->raiseAlertIfOutOfRange($observation);
        }

        return new ObservationResource($observation->fresh()->load(['recordedBy', 'alerts']));
    }

    /**
     * Hides a reading from the active list without losing it — clinical
     * readings can be produced in a compliance review, so a mis-recorded
     * entry gets archived rather than deleted outright.
     */
    public function archive(Request $request, Observation $observation)
    {
        abort_unless($request->user()->ownsTenant($observation->tenant_id), 403);

        $validated = $request->validate(['archived' => ['required', 'boolean']]);

        $observation->update(['archived_at' => $validated['archived'] ? now() : null]);

        return new ObservationResource($observation->fresh()->load(['recordedBy', 'alerts']));
    }

    protected function raiseAlertIfOutOfRange(Observation $observation): void
    {
        $breach = ObservationThresholds::check($observation->type, $observation->value);

        if ($breach) {
            ClinicalAlert::create([
                'tenant_id' => $observation->tenant_id,
                'service_user_id' => $observation->service_user_id,
                'observation_id' => $observation->id,
                'message' => $breach['message'],
                'severity' => $breach['severity'],
            ]);
        }
    }
}
