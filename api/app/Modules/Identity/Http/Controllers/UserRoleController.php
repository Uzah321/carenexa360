<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Http\Requests\StoreUserRequest;
use App\Modules\Identity\Http\Requests\UpdateUserRoleRequest;
use App\Modules\Identity\Http\Resources\UserRoleResource;
use App\Modules\Identity\Support\AdministrationRoles;
use App\Modules\Identity\Support\DefaultRoles;
use App\Modules\Staff\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserRoleController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(AdministrationRoles::ALLOWED), 403);

        $users = User::whereDoesntHave('roles', fn ($q) => $q->where('name', DefaultRoles::FAMILY_MEMBER))
            ->with(['roles', 'staffProfile'])
            ->orderBy('name')
            ->get();

        return UserRoleResource::collection($users);
    }

    public function store(StoreUserRequest $request)
    {
        $tenantId = $request->user()->tenant_id;

        $user = DB::transaction(function () use ($request, $tenantId) {
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

            // Every "who can this be assigned to" list in the app (Schedule,
            // visit reassignment, witness selection, the Staff directory
            // itself) reads from StaffProfile, not User+role directly — a
            // user created here without one is invisible everywhere despite
            // having a real role, which is exactly what happened before this
            // fix. Family Member accounts never reach this endpoint (index()
            // excludes them), so every user created here is staff.
            StaffProfile::create([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'employment_status' => 'active',
            ]);

            return $user;
        });

        return (new UserRoleResource($user->load(['roles', 'staffProfile'])))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateUserRoleRequest $request, User $user)
    {
        // A platform admin managing another tenant's user has the wrong
        // "team" context active (ResolveTenant scoped it to the admin's own
        // — null — tenant, not this user's), which would assign the role
        // under the wrong team id. Force it to the target user's tenant.
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);

        $role = Role::where('name', $request->validated('role'))
            ->where('tenant_id', $user->tenant_id)
            ->firstOrFail();

        $user->syncRoles([$role]);

        return new UserRoleResource($user->fresh()->load(['roles', 'staffProfile']));
    }
}
