<?php

namespace App\Modules\Medications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Medications\Http\Requests\StoreMedicationAdministrationRequest;
use App\Modules\Medications\Http\Resources\MedicationAdministrationResource;
use App\Modules\Medications\Models\Medication;
use Illuminate\Http\Request;

class MedicationAdministrationController extends Controller
{
    public function index(Request $request, Medication $medication)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $medication->tenant_id,
            403
        );

        return MedicationAdministrationResource::collection(
            $medication->administrations()->with(['administeredBy', 'witness'])->orderByDesc('created_at')->get()
        );
    }

    public function store(StoreMedicationAdministrationRequest $request, Medication $medication)
    {
        abort_unless($request->user()->tenant_id === $medication->tenant_id, 403);

        $administration = $medication->administrations()->create([
            ...$request->validated(),
            'tenant_id' => $medication->tenant_id,
            'administered_at' => $request->validated('administered_at') ?? now(),
            'administered_by' => $request->user()->id,
        ]);

        return (new MedicationAdministrationResource($administration->load(['administeredBy', 'witness'])))
            ->response()
            ->setStatusCode(201);
    }
}
