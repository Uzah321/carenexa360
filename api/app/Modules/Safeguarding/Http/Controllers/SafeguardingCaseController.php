<?php

namespace App\Modules\Safeguarding\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Safeguarding\Http\Requests\StoreSafeguardingCaseRequest;
use App\Modules\Safeguarding\Http\Requests\UpdateSafeguardingCaseRequest;
use App\Modules\Safeguarding\Http\Resources\SafeguardingCaseResource;
use App\Modules\Safeguarding\Models\SafeguardingCase;
use App\Modules\Safeguarding\Support\SafeguardingRoles;
use Illuminate\Http\Request;

class SafeguardingCaseController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(SafeguardingRoles::ALLOWED), 403);

        $cases = SafeguardingCase::with(['serviceUser', 'reportedBy'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return SafeguardingCaseResource::collection($cases);
    }

    public function store(StoreSafeguardingCaseRequest $request)
    {
        abort_unless($request->user()->hasAnyRole(SafeguardingRoles::ALLOWED), 403);

        $case = SafeguardingCase::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'status' => 'reported',
            'reported_by' => $request->user()->id,
        ]);

        return (new SafeguardingCaseResource($case->load(['serviceUser', 'reportedBy'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, SafeguardingCase $safeguardingCase)
    {
        abort_unless($request->user()->hasAnyRole(SafeguardingRoles::ALLOWED), 403);
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $safeguardingCase->tenant_id,
            403
        );

        return new SafeguardingCaseResource($safeguardingCase->load(['serviceUser', 'reportedBy', 'documents']));
    }

    public function update(UpdateSafeguardingCaseRequest $request, SafeguardingCase $safeguardingCase)
    {
        abort_unless($request->user()->hasAnyRole(SafeguardingRoles::ALLOWED), 403);
        abort_unless($request->user()->ownsTenant($safeguardingCase->tenant_id), 403);

        $safeguardingCase->update($request->validated());

        return new SafeguardingCaseResource($safeguardingCase->fresh()->load(['serviceUser', 'reportedBy']));
    }
}
