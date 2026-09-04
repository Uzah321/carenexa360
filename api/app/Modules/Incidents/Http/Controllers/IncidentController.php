<?php

namespace App\Modules\Incidents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Incidents\Http\Requests\StoreIncidentRequest;
use App\Modules\Incidents\Http\Requests\UpdateIncidentRequest;
use App\Modules\Incidents\Http\Resources\IncidentResource;
use App\Modules\Incidents\Models\Incident;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $incidents = Incident::with(['serviceUser', 'reportedBy', 'assignedTo'])
            ->when($request->query('service_user_id'), fn ($q, $id) => $q->where('service_user_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('severity'), fn ($q, $severity) => $q->where('severity', $severity))
            ->orderByDesc('created_at')
            ->paginate(20);

        return IncidentResource::collection($incidents);
    }

    public function store(StoreIncidentRequest $request)
    {
        $incident = Incident::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'status' => 'reported',
            'reported_by' => $request->user()->id,
        ]);

        return (new IncidentResource($incident->load(['serviceUser', 'reportedBy', 'assignedTo'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Incident $incident)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $incident->tenant_id,
            403
        );

        return new IncidentResource($incident->load(['serviceUser', 'reportedBy', 'assignedTo', 'reviewedBy']));
    }

    public function update(UpdateIncidentRequest $request, Incident $incident)
    {
        abort_unless($request->user()->ownsTenant($incident->tenant_id), 403);

        $attributes = $request->validated();

        if (($attributes['status'] ?? null) === 'reviewed' && $incident->status !== 'reviewed') {
            $attributes['reviewed_by'] = $request->user()->id;
            $attributes['reviewed_at'] = now();
        }

        if (($attributes['status'] ?? null) === 'closed' && $incident->status !== 'closed') {
            $attributes['closed_at'] = now();
        }

        $incident->update($attributes);

        return new IncidentResource($incident->fresh()->load(['serviceUser', 'reportedBy', 'assignedTo', 'reviewedBy']));
    }
}
