<?php

namespace App\Modules\Analytics\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Analytics\Support\AnalyticsRoles;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Medications\Models\Medication;
use App\Modules\Medications\Models\MedicationAdministration;
use App\Modules\Organization\Models\Tenant;
use App\Modules\ServiceUsers\Http\Resources\ServiceUserResource;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Training\Models\TrainingRecord;
use App\Modules\Visits\Http\Resources\VisitResource;
use App\Modules\Visits\Models\Visit;
use App\Support\Time\TenantClock;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TodayController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(AnalyticsRoles::ALLOWED), 403);

        $date = $request->query('date') ?: TenantClock::today($request->user()->tenant_id);

        $visits = Visit::whereDate('visit_date', $date)
            ->with(['serviceUser', 'carer'])
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'date' => $date,
            'stats' => $this->stats($request),
            'visits' => VisitResource::collection($visits)->resolve($request),
        ]);
    }

    public function snapshot(Request $request, ServiceUser $serviceUser)
    {
        abort_unless($request->user()->hasAnyRole(AnalyticsRoles::ALLOWED), 403);
        abort_unless(
            $request->user()->isPlatformAdmin() || $request->user()->tenant_id === $serviceUser->tenant_id,
            403
        );

        $carePlan = CarePlan::where('service_user_id', $serviceUser->id)
            ->where('status', 'active')
            ->with('sections')
            ->first();

        $medications = Medication::where('service_user_id', $serviceUser->id)
            ->where('status', 'active')
            ->get()
            ->map(function (Medication $medication) {
                $latest = MedicationAdministration::where('medication_id', $medication->id)
                    ->orderByDesc('administered_at')
                    ->first();

                return [
                    'id' => $medication->id,
                    'name' => $medication->name,
                    'dose' => $medication->dose,
                    'latest_administration' => $latest ? [
                        'status' => $latest->status,
                        'administered_at' => $latest->administered_at,
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'service_user' => (new ServiceUserResource($serviceUser))->resolve($request),
            'care_plan_sections' => $carePlan
                ? $carePlan->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'area' => $section->area,
                    'goal' => $section->goal,
                    'status' => $section->status,
                ])->values()
                : [],
            'medications' => $medications,
        ]);
    }

    protected function stats(Request $request): array
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        $warningDays = (int) (Tenant::find($request->user()->tenant_id)?->setting('training_expiry_warning_days') ?? 30);

        $trainingExpiringSoon = TrainingRecord::whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>', now())
            ->whereDate('expiry_date', '<=', now()->addDays($warningDays))
            ->orderBy('expiry_date');

        $missedVisitsThisWeek = Visit::where('status', 'missed')
            ->whereBetween('visit_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->count();

        $openNonCriticalIncidents = Incident::where('status', '!=', 'closed')
            ->whereIn('severity', ['low', 'medium'])
            ->count();

        return [
            'training_expiring_soon' => [
                'count' => (clone $trainingExpiringSoon)->count(),
                'soonest_expiry_date' => (clone $trainingExpiringSoon)->first()?->expiry_date?->toDateString(),
            ],
            'missed_visits_this_week' => $missedVisitsThisWeek,
            'open_incidents' => $openNonCriticalIncidents,
            'mar_accuracy_pct' => $this->marAccuracyPct(),
            'rota_coverage_pct' => $this->rotaCoveragePct($weekStart, $weekEnd),
        ];
    }

    protected function marAccuracyPct(): ?float
    {
        $window = MedicationAdministration::where('created_at', '>=', now()->subDays(30))
            ->where('status', '!=', 'prn');

        $total = (clone $window)->count();

        if ($total === 0) {
            return null;
        }

        $administered = (clone $window)->where('status', 'administered')->count();

        return round(($administered / $total) * 100, 1);
    }

    protected function rotaCoveragePct(Carbon $weekStart, Carbon $weekEnd): ?float
    {
        $window = Visit::whereBetween('visit_date', [$weekStart->toDateString(), $weekEnd->toDateString()]);

        $total = (clone $window)->count();

        if ($total === 0) {
            return null;
        }

        $assigned = (clone $window)->whereNotNull('carer_id')->count();

        return round(($assigned / $total) * 100, 1);
    }
}
