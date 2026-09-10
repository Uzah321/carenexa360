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
            'tenant_id' => $serviceUser->tenant_id,
            'recorded_by' => $request->user()->id,
            'recorded_at' => $request->validated('recorded_at') ?? now(),
        ]);

        $breach = ObservationThresholds::check($observation->type, $observation->value);

        if ($breach) {
            ClinicalAlert::create([
                'tenant_id' => $serviceUser->tenant_id,
                'service_user_id' => $serviceUser->id,
                'observation_id' => $observation->id,
                'message' => $breach['message'],
                'severity' => $breach['severity'],
            ]);
        }

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

        $observation->update($request->validated());

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
}
