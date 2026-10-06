<?php

namespace App\Modules\Quality\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Quality\Http\Requests\SaveComplaintRequest;
use App\Modules\Quality\Http\Resources\ComplaintResource;
use App\Modules\Quality\Models\Complaint;
use App\Modules\Quality\Support\QualityRoles;
use App\Notifications\AssignmentMessages;
use App\Support\AssignmentNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    private const RELATIONS = ['serviceUser', 'assignedTo'];

    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(QualityRoles::ALLOWED), 403);

        $complaints = Complaint::with(self::RELATIONS)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('service_user_id'), fn ($q, $id) => $q->where('service_user_id', $id))
            ->orderByDesc('received_date')
            ->orderByDesc('id')
            ->paginate(20);

        return ComplaintResource::collection($complaints);
    }

    public function store(SaveComplaintRequest $request)
    {
        $attributes = $request->validated();

        $complaint = Complaint::create([
            ...$attributes,
            'tenant_id' => $request->user()->tenant_id,
            'status' => $attributes['status'] ?? 'received',
            'response_due_date' => $attributes['response_due_date']
                ?? Carbon::parse($attributes['received_date'])->addDays(Complaint::RESPONSE_DAYS)->toDateString(),
            'created_by' => $request->user()->id,
        ]);

        AssignmentNotifier::notify($complaint->assigned_to, AssignmentMessages::complaint($complaint));

        return (new ComplaintResource($complaint->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function show(Request $request, Complaint $complaint)
    {
        abort_unless($request->user()->hasAnyRole(QualityRoles::ALLOWED), 403);
        abort_unless($request->user()->ownsTenant($complaint->tenant_id), 403);

        return new ComplaintResource($complaint->load(self::RELATIONS));
    }

    public function update(SaveComplaintRequest $request, Complaint $complaint)
    {
        abort_unless($request->user()->ownsTenant($complaint->tenant_id), 403);

        $attributes = $request->validated();

        // Closing it out stamps the date it was resolved, unless one was given.
        if (in_array($attributes['status'] ?? null, ['resolved', 'closed'], true) && ! $complaint->resolved_date && empty($attributes['resolved_date'])) {
            $attributes['resolved_date'] = now()->toDateString();
        }

        $previousAssignee = $complaint->assigned_to;
        $complaint->update($attributes);

        AssignmentNotifier::notifyIfChanged($previousAssignee, $complaint->assigned_to, AssignmentMessages::complaint($complaint));

        return new ComplaintResource($complaint->fresh()->load(self::RELATIONS));
    }
}
