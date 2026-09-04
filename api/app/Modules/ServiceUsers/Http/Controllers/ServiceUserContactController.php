<?php

namespace App\Modules\ServiceUsers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Identity\Support\DefaultRoles;
use App\Modules\ServiceUsers\Http\Requests\GrantPortalAccessRequest;
use App\Modules\ServiceUsers\Http\Requests\StoreServiceUserContactRequest;
use App\Modules\ServiceUsers\Http\Resources\ServiceUserContactResource;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\ServiceUsers\Models\ServiceUserContact;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class ServiceUserContactController extends Controller
{
    public function index(Request $request, ServiceUser $serviceUser)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $serviceUser->tenant_id,
            403
        );

        return ServiceUserContactResource::collection($serviceUser->contacts()->get());
    }

    public function store(StoreServiceUserContactRequest $request, ServiceUser $serviceUser)
    {
        $contact = $serviceUser->contacts()->create([
            ...$request->validated(),
            'tenant_id' => $serviceUser->tenant_id,
        ]);

        return new ServiceUserContactResource($contact);
    }

    public function destroy(Request $request, ServiceUser $serviceUser, int $contact)
    {
        abort_unless($request->user()->ownsTenant($serviceUser->tenant_id), 403);

        $serviceUser->contacts()->where('id', $contact)->firstOrFail()->delete();

        return response()->noContent();
    }

    public function grantPortalAccess(GrantPortalAccessRequest $request, ServiceUser $serviceUser, ServiceUserContact $contact)
    {
        abort_unless($request->user()->ownsTenant($serviceUser->tenant_id), 403);
        abort_unless($contact->service_user_id === $serviceUser->id, 404);
        abort_if($contact->user_id, 422, 'This contact already has portal access.');

        $user = User::create([
            'tenant_id' => $serviceUser->tenant_id,
            'name' => $contact->name,
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
        ]);

        $role = Role::where('name', DefaultRoles::FAMILY_MEMBER)
            ->where('tenant_id', $serviceUser->tenant_id)
            ->firstOrFail();
        $user->assignRole($role);

        $contact->update(['user_id' => $user->id]);

        return new ServiceUserContactResource($contact->fresh());
    }
}
