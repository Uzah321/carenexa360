<?php

namespace App\Modules\Compliance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compliance\Http\Requests\StoreComplianceRequirementRequest;
use App\Modules\Compliance\Http\Requests\UpdateComplianceRequirementRequest;
use App\Modules\Compliance\Http\Resources\ComplianceRequirementResource;
use App\Modules\Compliance\Models\ComplianceRequirement;
use App\Modules\Training\Support\ComplianceRoles;
use Illuminate\Http\Request;

class ComplianceRequirementController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(ComplianceRoles::ALLOWED), 403);

        $requirements = ComplianceRequirement::with('responsibleUser')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('category'), fn ($q, $category) => $q->where('category', $category))
            ->orderBy('renewal_date')
            ->get();

        $expiryStatus = $request->query('expiry_status');
        if ($expiryStatus) {
            $requirements = $requirements->filter(fn (ComplianceRequirement $r) => $r->expiry_status === $expiryStatus)->values();
        }

        return ComplianceRequirementResource::collection($requirements);
    }

    public function store(StoreComplianceRequirementRequest $request)
    {
        abort_unless($request->user()->hasAnyRole(ComplianceRoles::ALLOWED), 403);

        $requirement = ComplianceRequirement::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'status' => $request->validated('status', 'pending'),
            'created_by' => $request->user()->id,
        ]);

        return (new ComplianceRequirementResource($requirement->load('responsibleUser')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ComplianceRequirement $complianceRequirement)
    {
        abort_unless($request->user()->hasAnyRole(ComplianceRoles::ALLOWED), 403);
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $complianceRequirement->tenant_id,
            403
        );

        return new ComplianceRequirementResource($complianceRequirement->load('responsibleUser'));
    }

    public function update(UpdateComplianceRequirementRequest $request, ComplianceRequirement $complianceRequirement)
    {
        abort_unless($request->user()->hasAnyRole(ComplianceRoles::ALLOWED), 403);
        abort_unless($request->user()->tenant_id === $complianceRequirement->tenant_id, 403);

        $complianceRequirement->update($request->validated());

        return new ComplianceRequirementResource($complianceRequirement->fresh()->load('responsibleUser'));
    }

    public function destroy(Request $request, ComplianceRequirement $complianceRequirement)
    {
        abort_unless($request->user()->hasAnyRole(ComplianceRoles::ALLOWED), 403);
        abort_unless($request->user()->tenant_id === $complianceRequirement->tenant_id, 403);

        $complianceRequirement->delete();

        return response()->json(null, 204);
    }
}
