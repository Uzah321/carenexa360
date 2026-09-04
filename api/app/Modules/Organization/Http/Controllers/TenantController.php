<?php

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\StoreTenantRequest;
use App\Modules\Organization\Http\Requests\UpdateTenantRequest;
use App\Modules\Organization\Http\Requests\UpdateTenantStatusRequest;
use App\Modules\Organization\Http\Resources\TenantResource;
use App\Modules\Organization\Models\Tenant;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isPlatformAdmin(), 403);

        return TenantResource::collection(
            Tenant::orderBy('name')->paginate(15)
        );
    }

    public function store(StoreTenantRequest $request)
    {
        $tenant = Tenant::create($request->validated())->fresh();

        return (new TenantResource($tenant))->response()->setStatusCode(201);
    }

    public function show(Request $request, Tenant $tenant)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $tenant->id,
            403
        );

        return new TenantResource($tenant);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant)
    {
        $attributes = $request->validated();

        if (array_key_exists('settings', $attributes)) {
            // Merge rather than replace — a request that only touches one
            // setting (e.g. just the geofence radius) must not wipe out
            // other settings saved in an earlier, separate request.
            $attributes['settings'] = array_merge($tenant->settings ?? [], $attributes['settings']);
        }

        $tenant->update($attributes);

        return new TenantResource($tenant->fresh());
    }

    public function updateStatus(UpdateTenantStatusRequest $request, Tenant $tenant)
    {
        $tenant->update(['status' => $request->validated('status')]);

        return new TenantResource($tenant->fresh());
    }
}
