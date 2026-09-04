<?php

namespace App\Modules\ServiceUsers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ServiceUsers\Http\Requests\StoreServiceUserRequest;
use App\Modules\ServiceUsers\Http\Requests\UpdateServiceUserRequest;
use App\Modules\ServiceUsers\Http\Resources\ServiceUserResource;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Http\Request;

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
        $serviceUser = ServiceUser::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
        ]);

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
        $serviceUser->update($request->validated());

        return new ServiceUserResource($serviceUser->fresh()->load('carers'));
    }

    public function destroy(Request $request, ServiceUser $serviceUser)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $serviceUser->tenant_id,
            403
        );

        // Soft delete (the model uses SoftDeletes) — the record and its
        // history are preserved, just excluded from default queries.
        $serviceUser->delete();

        return response()->noContent();
    }
}
