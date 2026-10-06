<?php

namespace App\Modules\CarePlanning\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CarePlanning\Http\Requests\StoreCarePlanRequest;
use App\Modules\CarePlanning\Http\Resources\CarePlanResource;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\CarePlanning\Support\CarePathway;
use App\Modules\CarePlanning\Support\HomeCarePlan;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Notifications\AssignmentMessages;
use App\Support\AssignmentNotifier;
use App\Support\RichText;
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
                ->with(['sections.responsibleStaff', 'riskAssessments.actionOwner', 'createdBy'])
                ->orderByDesc('version')
                ->get()
        );
    }

    public function store(StoreCarePlanRequest $request, ServiceUser $serviceUser)
    {
        $previousPlan = $serviceUser->carePlans()->where('status', 'active')->with(['sections', 'riskAssessments'])->first();

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
                'home_care_plan' => HomeCarePlan::normalize($request->validated('home_care_plan')),
            ]);

            foreach ($request->validated('sections') as $section) {
                $carePlan->sections()->create([
                    ...$section,
                    'tenant_id' => $serviceUser->tenant_id,
                    'status' => $section['status'] ?? 'ongoing',
                ]);
            }

            foreach ($request->validated('risk_assessments') ?? [] as $riskAssessment) {
                $carePlan->riskAssessments()->create([
                    ...$riskAssessment,
                    'tenant_id' => $serviceUser->tenant_id,
                    'details' => RichText::sanitize($riskAssessment['details'] ?? null),
                    'contingency_plan_required' => (bool) ($riskAssessment['contingency_plan_required'] ?? false),
                    // A contingency plan only stands while one is required.
                    'contingency_plan' => ($riskAssessment['contingency_plan_required'] ?? false)
                        ? RichText::sanitize($riskAssessment['contingency_plan'] ?? null)
                        : null,
                    // Medication details only mean something on a medication
                    // assessment — drop any stray ones sent with a general one.
                    'medication_details' => $riskAssessment['type'] === 'medication'
                        ? ($riskAssessment['medication_details'] ?? null)
                        : null,
                ]);
            }

            return $carePlan;
        });

        $this->notifyNewResponsibilities($serviceUser, $previousPlan, $carePlan->load(['sections', 'riskAssessments']));

        return new CarePlanResource($carePlan->load(['sections', 'riskAssessments', 'createdBy']));
    }

    /**
     * Every save is a whole new version, so "assigned" means given an area
     * or risk this version that they didn't already hold on the last one —
     * otherwise each unrelated edit would re-email everyone on the plan.
     * One email per person, listing all of it.
     */
    protected function notifyNewResponsibilities(ServiceUser $serviceUser, ?CarePlan $previous, CarePlan $current): void
    {
        $held = collect([
            ...($previous?->sections ?? collect())->map(fn ($s) => "section|{$s->area}|{$s->responsible_staff_id}"),
            ...($previous?->riskAssessments ?? collect())->map(fn ($r) => "risk|{$r->hazard}|{$r->action_owner_id}"),
        ])->flip();

        $newSections = $current->sections
            ->filter(fn ($s) => $s->responsible_staff_id && ! $held->has("section|{$s->area}|{$s->responsible_staff_id}"))
            ->groupBy('responsible_staff_id');
        $newRisks = $current->riskAssessments
            ->filter(fn ($r) => $r->action_owner_id && ! $held->has("risk|{$r->hazard}|{$r->action_owner_id}"))
            ->groupBy('action_owner_id');

        foreach ($newSections->keys()->merge($newRisks->keys())->unique() as $userId) {
            AssignmentNotifier::notify((int) $userId, AssignmentMessages::carePlan(
                $serviceUser,
                $current->version,
                ($newSections[$userId] ?? collect())->all(),
                ($newRisks[$userId] ?? collect())->all(),
            ));
        }
    }

    /** Where the client is on the care pathway (System Settings → Care Pathway). */
    public function pathway(Request $request, ServiceUser $serviceUser)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $serviceUser->tenant_id,
            403
        );

        return response()->json(['data' => CarePathway::for($serviceUser)]);
    }

    public function show(Request $request, CarePlan $carePlan)
    {
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $carePlan->tenant_id,
            403
        );

        return new CarePlanResource($carePlan->load(['sections.responsibleStaff', 'riskAssessments.actionOwner', 'createdBy']));
    }
}
