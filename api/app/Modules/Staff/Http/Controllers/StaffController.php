<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Staff\Http\Requests\StoreStaffRequest;
use App\Modules\Staff\Http\Requests\UpdateStaffRequest;
use App\Modules\Staff\Http\Resources\StaffResource;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Staff\Support\StaffRoles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class StaffController extends Controller
{
    /**
     * Deliberately open to every authenticated tenant staff member (not
     * gated by StaffRoles) — this list backs carer-assignment and
     * witness-selection dropdowns across the app. StaffResource hides the
     * HR-sensitive fields from anyone outside StaffRoles instead.
     */
    public function index(Request $request)
    {
        return StaffResource::collection(
            StaffProfile::with('user')
                ->orderByDesc('created_at')
                ->paginate(max(1, min((int) $request->query('per_page', 15), 100)))
        );
    }

    public function store(StoreStaffRequest $request)
    {
        abort_unless($request->user()->hasAnyRole(StaffRoles::ALLOWED), 403);

        $tenantId = $request->user()->tenant_id;

        $staff = DB::transaction(function () use ($request, $tenantId) {
            $user = User::create([
                'tenant_id' => $tenantId,
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
            ]);

            $role = Role::where('name', $request->validated('role'))
                ->where('tenant_id', $tenantId)
                ->firstOrFail();
            $user->assignRole($role);

            return StaffProfile::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'branch_id' => $request->validated('branch_id'),
                'employee_number' => $request->validated('employee_number'),
                'job_title' => $request->validated('job_title'),
                'employment_start_date' => $request->validated('employment_start_date'),
                'skills' => $request->validated('skills', []),
                'employment_status' => $request->validated('employment_status', 'active'),
            ]);
        });

        return (new StaffResource($staff->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, StaffProfile $staff)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $staff->tenant_id,
            403
        );

        return new StaffResource($staff->load('user'));
    }

    public function update(UpdateStaffRequest $request, StaffProfile $staff)
    {
        abort_unless($request->user()->hasAnyRole(StaffRoles::ALLOWED), 403);
        abort_unless($request->user()->ownsTenant($staff->tenant_id), 403);

        $staff->update($request->validated());

        return new StaffResource($staff->fresh()->load('user'));
    }
}
