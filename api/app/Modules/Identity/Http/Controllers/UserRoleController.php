<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Http\Requests\UpdateUserRoleRequest;
use App\Modules\Identity\Http\Resources\UserRoleResource;
use App\Modules\Identity\Support\AdministrationRoles;
use App\Modules\Identity\Support\DefaultRoles;
use Illuminate\Http\Request;
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
