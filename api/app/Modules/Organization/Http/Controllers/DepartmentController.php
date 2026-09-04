<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\StoreDepartmentRequest;
use App\Modules\Organization\Http\Resources\DepartmentResource;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request, Tenant $tenant)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $tenant->id,
            403
        );

        return DepartmentResource::collection(
            Department::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->paginate(15)
        );
    }

    public function store(StoreDepartmentRequest $request, Tenant $tenant)
    {
        $department = Department::create([
            ...$request->validated(),
            'tenant_id' => $tenant->id,
        ]);

        return new DepartmentResource($department);
    }
}
