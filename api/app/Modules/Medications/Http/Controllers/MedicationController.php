<?php

namespace App\Modules\Medications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Medications\Http\Requests\StoreMedicationRequest;
use App\Modules\Medications\Http\Requests\UpdateMedicationRequest;
use App\Modules\Medications\Http\Resources\MedicationResource;
use App\Modules\Medications\Models\Medication;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Http\Request;

class MedicationController extends Controller
{
    public function index(Request $request, ServiceUser $serviceUser)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $serviceUser->tenant_id,
            403
        );

        return MedicationResource::collection(
            $serviceUser->medications()->orderByDesc('created_at')->get()
        );
    }

    public function store(StoreMedicationRequest $request, ServiceUser $serviceUser)
    {
        abort_unless($request->user()->ownsTenant($serviceUser->tenant_id), 403);

        $medication = $serviceUser->medications()->create([
            ...$request->validated(),
            'tenant_id' => $serviceUser->tenant_id,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        return (new MedicationResource($medication))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Medication $medication)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $medication->tenant_id,
            403
        );

        return new MedicationResource($medication->load('administrations.administeredBy', 'administrations.witness'));
    }

    public function update(UpdateMedicationRequest $request, Medication $medication)
    {
        abort_unless($request->user()->ownsTenant($medication->tenant_id), 403);

        $medication->update($request->validated());

        return new MedicationResource($medication->fresh());
    }
}
