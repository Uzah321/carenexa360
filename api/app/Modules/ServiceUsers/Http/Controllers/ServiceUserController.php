<?php

namespace App\Modules\ServiceUsers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Support\AdministrationRoles;
use App\Modules\ServiceUsers\Http\Requests\StoreServiceUserRequest;
use App\Modules\ServiceUsers\Http\Requests\UpdateServiceUserRequest;
use App\Modules\ServiceUsers\Http\Resources\ServiceUserResource;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Notifications\AssignmentMessages;
use App\Support\AssignmentNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ServiceUserController extends Controller
{
    /**
     * Search and status filtering are applied server-side, before pagination —
     * filtering the current page in the browser instead would silently miss
     * every match that happens to sit on another page.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');
        // Capped so a caller can't ask for an unbounded page.
        $perPage = min(200, max(1, (int) $request->query('per_page', 15)));

        return ServiceUserResource::collection(
            ServiceUser::with('carers')
                ->when($search !== '', function ($query) use ($search) {
                    $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

                    $query->where(function ($q) use ($term) {
                        $q->whereRaw('LOWER(first_name) LIKE LOWER(?)', [$term])
                            ->orWhereRaw('LOWER(last_name) LIKE LOWER(?)', [$term])
                            ->orWhereRaw("LOWER(first_name || ' ' || last_name) LIKE LOWER(?)", [$term])
                            ->orWhereRaw("LOWER(COALESCE(preferred_name, '')) LIKE LOWER(?)", [$term]);
                    });
                })
                ->when(
                    in_array($status, ['active', 'inactive', 'discharged'], true),
                    fn ($query) => $query->where('status', $status)
                )
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate($perPage)
                ->withQueryString()
        );
    }

    public function store(StoreServiceUserRequest $request)
    {
        $attributes = $request->validated();
        $carerIds = Arr::pull($attributes, 'carer_ids');

        $serviceUser = ServiceUser::create([
            ...$attributes,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        AssignmentNotifier::notify($serviceUser->care_manager_id, AssignmentMessages::careManager($serviceUser));
        $this->syncCarers($serviceUser, $carerIds);

        return (new ServiceUserResource($serviceUser->fresh()->load('carers')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, ServiceUser $serviceUser)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $serviceUser->tenant_id,
            403
        );

        return new ServiceUserResource($serviceUser->load('carers'));
    }

    public function update(UpdateServiceUserRequest $request, ServiceUser $serviceUser)
    {
        $attributes = $request->validated();
        $carerIds = Arr::pull($attributes, 'carer_ids');

        $previousManager = $serviceUser->care_manager_id;
        $serviceUser->update($attributes);

        AssignmentNotifier::notifyIfChanged($previousManager, $serviceUser->care_manager_id, AssignmentMessages::careManager($serviceUser));
        $this->syncCarers($serviceUser, $carerIds);

        return new ServiceUserResource($serviceUser->fresh()->load('carers'));
    }

    /**
     * Replaces the client's care team with exactly these carers (null = not
     * sent, leave it alone) and emails only the ones newly added — carers
     * already on the team, or removed from it, hear nothing.
     */
    protected function syncCarers(ServiceUser $serviceUser, ?array $carerIds): void
    {
        if ($carerIds === null) {
            return;
        }

        $changes = $serviceUser->carers()->syncWithPivotValues(
            array_map('intval', $carerIds),
            ['tenant_id' => $serviceUser->tenant_id],
        );

        foreach ($changes['attached'] as $carerId) {
            AssignmentNotifier::notify((int) $carerId, AssignmentMessages::carer($serviceUser));
        }
    }

    /**
     * Stops the client appearing as active (scheduling, Today, reports)
     * while keeping their whole record — the reversible alternative to
     * destroy() below.
     */
    public function deactivate(Request $request, ServiceUser $serviceUser)
    {
        $this->authorizeAdministration($request, $serviceUser);

        $serviceUser->update(['status' => 'inactive']);

        return new ServiceUserResource($serviceUser->fresh()->load('carers'));
    }

    public function reactivate(Request $request, ServiceUser $serviceUser)
    {
        $this->authorizeAdministration($request, $serviceUser);

        $serviceUser->update(['status' => 'active']);

        return new ServiceUserResource($serviceUser->fresh()->load('carers'));
    }

    /**
     * Permanent — there is no archive to restore from. The database cascades
     * the client's own records (care plans, notes, MAR, observations,
     * visits…); incidents and safeguarding cases are organisational records
     * and keep their row with the client link cleared. Their uploaded files
     * are removed from storage here, since a database cascade can't do that.
     */
    public function destroy(Request $request, ServiceUser $serviceUser)
    {
        $this->authorizeAdministration($request, $serviceUser);

        DB::transaction(function () use ($serviceUser) {
            foreach ($serviceUser->documents as $document) {
                Storage::disk('local')->delete($document->path);
                $document->delete();
            }

            $serviceUser->forceDelete();
        });

        return response()->noContent();
    }

    protected function authorizeAdministration(Request $request, ServiceUser $serviceUser): void
    {
        abort_unless($request->user()->hasAnyRole(AdministrationRoles::ALLOWED), 403);
        abort_unless($request->user()->ownsTenant($serviceUser->tenant_id), 403);
    }
}
