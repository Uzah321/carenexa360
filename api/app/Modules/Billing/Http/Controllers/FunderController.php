<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Http\Requests\StoreFunderRequest;
use App\Modules\Billing\Http\Requests\UpdateFunderRequest;
use App\Modules\Billing\Http\Resources\FunderResource;
use App\Modules\Billing\Models\Funder;
use App\Modules\Billing\Support\FinanceRoles;
use Illuminate\Http\Request;

class FunderController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(FinanceRoles::ALLOWED), 403);

        return FunderResource::collection(Funder::orderBy('name')->get());
    }

    public function store(StoreFunderRequest $request)
    {
        abort_unless($request->user()->hasAnyRole(FinanceRoles::ALLOWED), 403);

        $funder = Funder::create([
            ...$request->validated(),
            'tenant_id' => $request->user()->tenant_id,
            'status' => 'active',
        ]);

        return (new FunderResource($funder))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Funder $funder)
    {
        abort_unless($request->user()->hasAnyRole(FinanceRoles::ALLOWED), 403);
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $funder->tenant_id,
            403
        );

        return new FunderResource($funder);
    }

    public function update(UpdateFunderRequest $request, Funder $funder)
    {
        abort_unless($request->user()->hasAnyRole(FinanceRoles::ALLOWED), 403);
        abort_unless($request->user()->ownsTenant($funder->tenant_id), 403);

        $funder->update($request->validated());

        return new FunderResource($funder->fresh());
    }
}
