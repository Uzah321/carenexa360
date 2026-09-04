<?php

namespace App\Modules\CarePlanning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CarePlanning\Http\Requests\StoreCarePlanRequest;
use App\Modules\CarePlanning\Http\Resources\CarePlanResource;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\ServiceUsers\Models\ServiceUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CarePlanController extends Controller
{
    public function index(Request $request, ServiceUser $serviceUser)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $serviceUser->tenant_id,
            403
        );

        return CarePlanResource::collection(
            $serviceUser->carePlans()
                ->with(['sections.responsibleStaff', 'createdBy'])
                ->orderByDesc('version')
                ->get()
        );
    }

    public function store(StoreCarePlanRequest $request, ServiceUser $serviceUser)
    {
        $carePlan = DB::transaction(function () use ($request, $serviceUser) {
            $serviceUser->carePlans()
                ->where('status', 'active')
                ->update(['status' => 'archived']);

            $nextVersion = (int) $serviceUser->carePlans()->max('version') + 1;

            $carePlan = $serviceUser->carePlans()->create([
                'tenant_id' => $serviceUser->tenant_id,
                'version' => $nextVersion,
                'status' => 'active',
                'effective_from' => $request->validated('effective_from'),
                'created_by' => $request->user()->id,
                'notes' => $request->validated('notes'),
            ]);

            foreach ($request->validated('sections') as $section) {
                $carePlan->sections()->create([
                    ...$section,
                    'tenant_id' => $serviceUser->tenant_id,
                    'status' => $section['status'] ?? 'ongoing',
                ]);
            }

            return $carePlan;
        });

        return new CarePlanResource($carePlan->load(['sections', 'createdBy']));
    }

    public function show(Request $request, CarePlan $carePlan)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $carePlan->tenant_id,
            403
        );

        return new CarePlanResource($carePlan->load(['sections.responsibleStaff', 'createdBy']));
    }
}
