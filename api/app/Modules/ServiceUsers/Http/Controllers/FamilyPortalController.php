<?php

namespace App\Modules\ServiceUsers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CarePlanning\Http\Resources\CarePlanResource;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\Documents\Http\Resources\DocumentResource;
use App\Modules\Incidents\Http\Resources\FamilyIncidentResource;
use App\Modules\Incidents\Models\Incident;
use App\Modules\ServiceUsers\Http\Resources\ServiceUserResource;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\ServiceUsers\Support\FamilyPortalAccess;
use App\Modules\Visits\Http\Resources\VisitResource;
use App\Modules\Visits\Models\Visit;
use Illuminate\Http\Request;

class FamilyPortalController extends Controller
{
    public function index(Request $request)
    {
        $serviceUserIds = FamilyPortalAccess::serviceUserIdsFor($request->user());

        return ServiceUserResource::collection(
            ServiceUser::whereIn('id', $serviceUserIds)->orderBy('first_name')->get()
        );
    }

    public function show(Request $request, ServiceUser $serviceUser)
    {
        abort_unless(
            in_array($serviceUser->id, FamilyPortalAccess::serviceUserIdsFor($request->user()), true),
            403
        );

        $carePlan = CarePlan::where('service_user_id', $serviceUser->id)
            ->where('status', 'active')
            ->with('sections')
            ->first();

        $upcomingVisits = Visit::where('service_user_id', $serviceUser->id)
            ->where('visit_date', '>=', now()->toDateString())
            ->with('carer')
            ->orderBy('visit_date')
            ->orderBy('start_time')
            ->limit(5)
            ->get();

        $recentVisits = Visit::where('service_user_id', $serviceUser->id)
            ->where('visit_date', '<', now()->toDateString())
            ->with('carer')
            ->orderByDesc('visit_date')
            ->orderByDesc('start_time')
            ->limit(5)
            ->get();

        $documents = $serviceUser->documents()->where('visible_to_family', true)->with('uploadedBy')->get();

        $incidents = Incident::where('service_user_id', $serviceUser->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'service_user' => (new ServiceUserResource($serviceUser))->resolve($request),
            'care_plan' => $carePlan ? (new CarePlanResource($carePlan))->resolve($request) : null,
            'upcoming_visits' => VisitResource::collection($upcomingVisits)->resolve($request),
            'recent_visits' => VisitResource::collection($recentVisits)->resolve($request),
            'documents' => DocumentResource::collection($documents)->resolve($request),
            'incidents' => FamilyIncidentResource::collection($incidents)->resolve($request),
        ]);
    }
}
