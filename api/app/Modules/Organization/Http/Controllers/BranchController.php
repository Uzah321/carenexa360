<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\StoreBranchRequest;
use App\Modules\Organization\Http\Requests\UpdateBranchRequest;
use App\Modules\Organization\Http\Resources\BranchResource;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request, Tenant $tenant)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $tenant->id,
            403
        );

        return BranchResource::collection(
            Branch::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->paginate(15)
        );
    }

    public function store(StoreBranchRequest $request, Tenant $tenant)
    {
        $branch = Branch::create([
            ...$request->validated(),
            'tenant_id' => $tenant->id,
        ]);

        return new BranchResource($branch);
    }

    public function update(UpdateBranchRequest $request, Tenant $tenant, Branch $branch)
    {
        abort_unless($branch->tenant_id === $tenant->id, 404);

        $branch->update($request->validated());

        return new BranchResource($branch->fresh());
    }

    public function updateStatus(Request $request, Tenant $tenant, Branch $branch)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $tenant->id,
            403
        );
        abort_unless($branch->tenant_id === $tenant->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(Branch::STATUSES)],
        ]);

        $branch->update(['status' => $validated['status']]);

        return new BranchResource($branch->fresh());
    }
}
