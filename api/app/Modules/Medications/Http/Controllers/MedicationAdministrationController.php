<?php

namespace App\Modules\Medications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Medications\Http\Requests\StoreMedicationAdministrationRequest;
use App\Modules\Medications\Http\Resources\MedicationAdministrationResource;
use App\Modules\Medications\Models\Medication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        abort_unless($request->user()->ownsTenant($medication->tenant_id), 403);

        $administration = DB::transaction(function () use ($request, $medication) {
            $administration = $medication->administrations()->create([
                ...$request->validated(),
                'tenant_id' => $medication->tenant_id,
                'administered_at' => $request->validated('administered_at') ?? now(),
                'administered_by' => $request->user()->id,
            ]);

            // A given dose comes off the stock count, never below zero — done
            // in one statement so two doses recorded at once can't both read
            // the same starting count.
            if (in_array($administration->status, ['administered', 'prn'], true) && $medication->tracksStock()) {
                Medication::whereKey($medication->id)->whereNotNull('stock_on_hand')
                    ->update(['stock_on_hand' => DB::raw('GREATEST(stock_on_hand - units_per_dose, 0)')]);
            }

            return $administration;
        });

        return (new MedicationAdministrationResource($administration->load(['administeredBy', 'witness'])))
            ->response()
            ->setStatusCode(201);
    }
}
