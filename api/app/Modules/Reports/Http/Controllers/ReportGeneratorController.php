<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Invoice;
use App\Modules\CarePlanning\Models\CarePlan;
use App\Modules\CarePlanning\Models\CarePlanRiskAssessment;
use App\Modules\CarePlanning\Models\CarePlanSection;
use App\Modules\CarePlanning\Support\HomeCarePlan;
use App\Modules\Documents\Models\Document;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Medications\Models\Medication;
use App\Modules\Medications\Models\MedicationAdministration;
use App\Modules\Observations\Models\ClinicalAlert;
use App\Modules\Observations\Models\Observation;
use App\Modules\Observations\Support\News2;
use App\Modules\Observations\Support\RangeScores;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\Tenant;
use App\Modules\Organization\Support\TenantSettings;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Reports\Support\OperationalReports;
use App\Modules\Reports\Support\ReportRoles;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Safeguarding\Models\SafeguardingCase;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Training\Models\TrainingRecord;
use App\Modules\Visits\Models\Visit;
use App\Support\Geo\Haversine;
use App\Support\Time\TenantClock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * The generic "pick a report, filter by date, generate, download" engine
 * behind the new /reports page — distinct from ReportController, which
 * still serves the older dashboard-style aggregate summary. Every handler
 * here returns the same shape: {title, columns[], rows[]} so the frontend
 * can render (and PDF-export) any of them with one generic table.
 */
class ReportGeneratorController extends Controller
{
    /**
     * Statuses that represent an invoice actually issued to the client —
     * excludes 'draft' (not yet sent, so not real committed revenue) and
     * 'cancelled' (voided). Mirrors OperationsDashboardController's same
     * constant so "revenue" means the same thing everywhere it's shown.
     */
    private const BILLED_STATUSES = ['sent', 'paid', 'overdue'];

    public function generate(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(ReportRoles::ALLOWED), 403);

        $validated = $request->validate([
            'key' => ['required', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $filters = [
            'from' => $validated['from'] ?? TenantClock::now($request->user()->tenant_id)->startOfMonth()->toDateString(),
            'to' => $validated['to'] ?? TenantClock::today($request->user()->tenant_id),
            'branch_id' => $validated['branch_id'] ?? null,
        ];

        try {
            $report = $this->dispatch($validated['key'], $filters);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(array_merge([
            'key' => $validated['key'],
            'generated_at' => now()->toIso8601String(),
            'filters' => $filters,
        ], $report));
    }

    private function dispatch(string $key, array $filters): array
    {
        $ops = new OperationalReports($filters);

        return match ($key) {
            // Client history, care delivery, workforce, rostering, travel and
            // finance reports live in OperationalReports.
            'care_history' => $ops->careHistory(),
            'care_pathway' => $ops->carePathway(),
            'review_history' => $ops->reviewHistory(),
            'daily_notes' => $ops->dailyNotes(),
            'family_contact_activity' => $ops->familyContactActivity(),
            'admission_discharge_history' => $ops->admissionsAndDischarges(),
            'new_admissions' => $ops->admissionsAndDischarges(admissionsOnly: true),
            'care_tasks' => $ops->careTasks(),
            'care_package_utilization' => $ops->carePackageUtilization(),
            'staff_attendance' => $ops->staffAttendance(),
            'clock_in_out' => $ops->clockInOut(),
            'overtime' => $ops->overtime(),
            'sickness' => $ops->sickness(),
            'unfilled_shifts' => $ops->unfilledShifts(),
            'staff_utilization' => $ops->staffUtilization(),
            'mileage_travel_time' => $ops->travelByDay('Mileage & Travel Time'),
            'travel_distance' => $ops->travelByDay('Travel Distance'),
            'mileage' => $ops->mileage(withReimbursement: false),
            'mileage_reimbursement' => $ops->mileage(withReimbursement: true),
            'assigned_vs_available' => $ops->staffingByDay('available'),
            'staffing_gaps' => $ops->staffingByDay('gaps'),
            'staff_shortages' => $ops->staffingByDay('shortages'),
            'overtime_risk' => $ops->overtimeRisk(),
            'client_carer_allocation' => $ops->clientCarerAllocation(),
            'background_checks' => $ops->backgroundChecks(),
            'funding_utilization' => $ops->fundingUtilization(),
            'profit_margin' => $ops->profitMargin(),
            'medication_stock' => $ops->medicationStock(),
            'wound_progress' => $ops->woundProgress(),
            'complaints' => $ops->complaints(),
            'spot_checks' => $ops->spotChecks(),
            'service_quality_indicators' => $ops->serviceQualityIndicators(),

            // Client / Service User
            'visit_history' => $this->visitList($filters, null, 'Visit History'),
            'missed_visits' => $this->visitList($filters, 'missed', 'Missed Visits'),
            'active_clients' => $this->clientsByStatus($filters, 'active', 'Active Clients'),
            'discharged_clients' => $this->clientsByStatus($filters, 'discharged', 'Discharged Clients'),
            'client_profile_summary' => $this->clientProfileSummary($filters),
            'care_plan_summary' => $this->carePlanSummary($filters),

            // Care Delivery
            'visit_status_breakdown' => $this->visitStatusBreakdown($filters),
            'cancelled_visits' => $this->visitList($filters, 'cancelled', 'Cancelled Visits'),
            'late_visits' => $this->lateVisits($filters),
            'care_hours_delivered' => $this->careHoursDelivered($filters),

            // Medication
            'emar_administration' => $this->medicationAdminList($filters, null, 'eMAR Administration Report'),
            'missed_medication' => $this->medicationAdminList($filters, 'missed', 'Missed Medication'),
            'refused_medication' => $this->medicationAdminList($filters, 'refused', 'Refused Medication'),
            'prn_medication_usage' => $this->medicationAdminList($filters, 'prn', 'PRN Medication Usage'),
            'medication_not_given' => $this->medicationNotGiven($filters),
            'not_given_reasons' => $this->notGivenReasons($filters),
            'unrecorded_doses' => $this->unrecordedDoses($filters),
            'late_medication' => $this->lateMedication($filters),
            'stock_checks' => $this->stockChecks($filters),
            'medication_errors' => $this->incidentList($filters, 'medication_error', 'Medication Errors'),

            // Clinical
            'bp_trends' => $this->observationTrend($filters, ['blood_pressure'], 'Blood Pressure Trends'),
            'glucose_trends' => $this->observationTrend($filters, ['blood_glucose'], 'Glucose Trends'),
            'spo2_trends' => $this->observationTrend($filters, ['oxygen_saturation'], 'Oxygen Saturation Trends'),
            'weight_bmi_trends' => $this->observationTrend($filters, ['weight', 'bmi'], 'Weight / BMI Trends'),
            'temperature_trends' => $this->observationTrend($filters, ['temperature'], 'Temperature Trends'),
            'pain_score_trends' => $this->observationTrend($filters, ['pain_score'], 'Pain Score Trends'),
            'hydration_trends' => $this->observationTrend($filters, ['fluid_intake', 'urine_output'], 'Nutrition & Hydration Trends'),
            'abnormal_observation_alerts' => $this->clinicalAlerts($filters),
            'news2_scores' => $this->news2Scores($filters, false),
            'news2_escalations' => $this->news2Scores($filters, true),

            // Incident & Safeguarding
            'falls' => $this->incidentList($filters, 'fall', 'Falls'),
            'injuries' => $this->incidentList($filters, 'injury', 'Injuries'),
            'all_incidents' => $this->incidentList($filters, null, 'All Incidents'),
            'safeguarding_concerns' => $this->safeguardingList($filters),
            'incident_status_breakdown' => $this->incidentBreakdown($filters, 'status', Incident::STATUSES, 'Open vs Closed Incidents'),
            'incident_severity_breakdown' => $this->incidentBreakdown($filters, 'severity', Incident::SEVERITIES, 'Incident Severity Trends'),
            'incident_frequency' => $this->incidentFrequency($filters),
            'corrective_actions' => $this->correctiveActions($filters),
            'unresolved_actions' => $this->unresolvedActions($filters),

            // Staff & Workforce
            'leave_report' => $this->leaveList($filters),
            'shift_coverage' => $this->shiftList($filters),
            'worked_hours' => $this->workedHours($filters),

            // Training & Compliance
            'expired_certifications' => $this->trainingList($filters, 'expired', 'Expired Certifications'),
            'certificates_expiring_soon' => $this->trainingList($filters, 'expiring_soon', 'Certificates Due to Expire'),
            'mandatory_training_completion' => $this->mandatoryTrainingCompletion($filters),
            'compliance_by_branch' => $this->trainingComplianceByBranch($filters),
            'staff_document_expiry' => $this->staffDocumentExpiry($filters),

            // Rostering
            'double_bookings' => $this->doubleBookings($filters),

            // Finance
            'invoices' => $this->invoiceList($filters, null, 'Invoices'),
            'outstanding_balances' => $this->invoiceList($filters, 'outstanding', 'Outstanding Balances'),
            'payments' => $this->invoiceList($filters, 'paid', 'Payments'),
            'revenue_breakdown' => $this->revenueBreakdown($filters),
            'care_hours_billed' => $this->careHoursBilled($filters),
            'payroll_cost' => $this->payrollCost($filters),

            // Quality & Audit
            'care_plan_reviews_overdue' => $this->overdueReviews($filters),
            'documentation_completeness' => $this->documentationCompleteness($filters),
            'care_consent' => $this->careConsent($filters),
            'risk_register' => $this->riskRegister($filters),

            // GPS / Visit Verification
            'trips_activity' => $this->tripsActivity($filters),
            'verified_checkins' => $this->checkinList($filters, 'verified'),
            'manual_overrides' => $this->checkinList($filters, 'overrides'),
            'suspicious_checkins' => $this->checkinList($filters, 'suspicious'),
            'checkin_distance' => $this->checkinList($filters, 'distance'),

            // Management
            'branch_performance' => $this->branchPerformance($filters),

            default => throw new InvalidArgumentException("Unknown or unavailable report key: {$key}"),
        };
    }

    // ---- Shared helpers -----------------------------------------------

    private function scopeByBranch($query, ?int $branchId, string $relation = 'serviceUser')
    {
        if (! $branchId) {
            return $query;
        }

        return $query->whereHas($relation, fn ($q) => $q->where('branch_id', $branchId));
    }

    private function tenantId(): ?int
    {
        return auth()->user()?->tenant_id;
    }

    /**
     * The report's from/to dates as UTC instants bounding the tenant's local
     * days — for filtering timestamp columns, which are stored in UTC.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function utcRange(array $filters): array
    {
        return [
            TenantClock::dayBoundsUtc($this->tenantId(), $filters['from'])[0],
            TenantClock::dayBoundsUtc($this->tenantId(), $filters['to'])[1],
        ];
    }

    /** A stored UTC timestamp as the tenant's local HH:MM. */
    private function localTime(?\DateTimeInterface $instant): string
    {
        return $instant ? TenantClock::local($this->tenantId(), $instant)->format('H:i') : '—';
    }

    private function timeToMinutes(string $time): int
    {
        [$h, $m] = array_map('intval', explode(':', $time));

        return $h * 60 + $m;
    }

    private function clientName(?ServiceUser $serviceUser): string
    {
        return $serviceUser ? trim("{$serviceUser->first_name} {$serviceUser->last_name}") : '—';
    }

    // ---- Client / Service User -----------------------------------------

    private function clientsByStatus(array $filters, string $status, string $title): array
    {
        $clients = $this->scopeByBranch(ServiceUser::where('status', $status), $filters['branch_id'], 'branch')
            ->orderBy('first_name')
            ->get();

        return [
            'title' => $title,
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'date_of_birth', 'label' => 'Date of Birth'],
                ['key' => 'nhs_number', 'label' => 'NHS Number'],
                ['key' => 'funding_source', 'label' => 'Funding'],
                ['key' => 'address', 'label' => 'Address'],
            ],
            'rows' => $clients->map(fn (ServiceUser $su) => [
                'name' => $this->clientName($su),
                'date_of_birth' => $su->date_of_birth?->toDateString() ?? '—',
                'nhs_number' => $su->nhs_number ?? '—',
                'funding_source' => $su->funding_source ?? '—',
                'address' => $su->address ?? '—',
            ]),
        ];
    }

    /** Every client in scope, whatever their status — a register-style overview. */
    private function clientProfileSummary(array $filters): array
    {
        $clients = $this->scopeByBranch(ServiceUser::query(), $filters['branch_id'], 'branch')
            ->with(['carePlans' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('first_name')
            ->get();

        return [
            'title' => 'Client Profile Summary',
            'columns' => [
                ['key' => 'name', 'label' => 'Name'],
                ['key' => 'date_of_birth', 'label' => 'Date of Birth'],
                ['key' => 'nhs_number', 'label' => 'NHS Number'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'funding_source', 'label' => 'Funding'],
                ['key' => 'allergies', 'label' => 'Allergies'],
                ['key' => 'diagnoses', 'label' => 'Diagnoses'],
                ['key' => 'care_plan', 'label' => 'Care Plan'],
            ],
            'rows' => $clients->map(fn (ServiceUser $su) => [
                'name' => $this->clientName($su),
                'date_of_birth' => $su->date_of_birth?->toDateString() ?? '—',
                'nhs_number' => $su->nhs_number ?? '—',
                'status' => $su->status,
                'funding_source' => $su->funding_source ?? '—',
                'allergies' => implode(', ', $su->allergies ?? []) ?: 'None recorded',
                'diagnoses' => implode(', ', $su->diagnoses ?? []) ?: '—',
                'care_plan' => ($plan = $su->carePlans->first()) ? "Version {$plan->version}" : 'None',
            ]),
        ];
    }

    // ---- Visits ---------------------------------------------------------

    private function visitList(array $filters, ?string $status, string $title): array
    {
        $visits = Visit::whereBetween('visit_date', [$filters['from'], $filters['to']])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with(['serviceUser', 'carer'])
            ->orderBy('visit_date')->orderBy('start_time')
            ->get();

        return [
            'title' => $title,
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'carer', 'label' => 'Carer'],
                ['key' => 'time', 'label' => 'Time'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $visits->map(fn (Visit $v) => [
                'date' => $v->visit_date->toDateString(),
                'client' => $this->clientName($v->serviceUser),
                'carer' => $v->carer->name ?? 'Unassigned',
                'time' => "{$v->start_time}–{$v->end_time}",
                'status' => str_replace('_', ' ', $v->status),
            ]),
        ];
    }

    private function visitStatusBreakdown(array $filters): array
    {
        $base = $this->scopeByBranch(
            Visit::whereBetween('visit_date', [$filters['from'], $filters['to']]),
            $filters['branch_id']
        );

        $total = (clone $base)->count();
        $counts = (clone $base)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'title' => 'Scheduled vs Completed Visits',
            'columns' => [
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'count', 'label' => 'Count'],
                ['key' => 'percentage', 'label' => '% of Total'],
            ],
            'rows' => collect(Visit::STATUSES)->map(fn ($s) => [
                'status' => str_replace('_', ' ', $s),
                'count' => (int) ($counts[$s] ?? 0),
                'percentage' => $total > 0 ? round((($counts[$s] ?? 0) / $total) * 100, 1).'%' : '0%',
            ]),
        ];
    }

    private function lateVisits(array $filters): array
    {
        $visits = Visit::whereBetween('visit_date', [$filters['from'], $filters['to']])
            ->whereNotNull('check_in_at')
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with(['serviceUser', 'carer'])
            ->orderBy('visit_date')
            ->get();

        $rows = $visits
            ->map(function (Visit $v) {
                $scheduledStart = TenantClock::wallClock($this->tenantId(), $v->visit_date->toDateString(), $v->start_time);
                $minutesLate = (int) round(($v->check_in_at->timestamp - $scheduledStart->timestamp) / 60);

                return [$v, $minutesLate];
            })
            ->filter(fn ($pair) => $pair[1] > (int) TenantSettings::for($this->tenantId(), 'late_arrival_minutes'))
            ->map(fn ($pair) => [
                'date' => $pair[0]->visit_date->toDateString(),
                'client' => $this->clientName($pair[0]->serviceUser),
                'carer' => $pair[0]->carer->name ?? 'Unassigned',
                'scheduled' => $pair[0]->start_time,
                'checked_in' => $this->localTime($pair[0]->check_in_at),
                'minutes_late' => $pair[1],
            ])
            ->sortByDesc('minutes_late')
            ->values();

        return [
            'title' => 'Late Visits',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'carer', 'label' => 'Carer'],
                ['key' => 'scheduled', 'label' => 'Scheduled'],
                ['key' => 'checked_in', 'label' => 'Checked In'],
                ['key' => 'minutes_late', 'label' => 'Minutes Late'],
            ],
            'rows' => $rows,
        ];
    }

    private function careHoursDelivered(array $filters): array
    {
        $visits = $this->scopeByBranch(
            Visit::where('status', 'completed')->whereBetween('visit_date', [$filters['from'], $filters['to']]),
            $filters['branch_id']
        )->get();

        $rows = $visits->groupBy(fn (Visit $v) => $v->visit_date->toDateString())
            ->map(function ($group, $date) {
                $hours = $group->sum(fn (Visit $v) => max(0, $this->timeToMinutes($v->end_time) - $this->timeToMinutes($v->start_time)) / 60);

                return ['date' => $date, 'visits' => $group->count(), 'hours' => round($hours, 2)];
            })
            ->sortBy('date')
            ->values();

        return [
            'title' => 'Care Hours Delivered',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'visits', 'label' => 'Completed Visits'],
                ['key' => 'hours', 'label' => 'Hours Delivered'],
            ],
            'rows' => $rows,
        ];
    }

    // ---- Medications ------------------------------------------------------

    /**
     * Older records carried the reason in the status itself, from before
     * "not given" took a reason — map them onto the same reasons so reports
     * count them together.
     */
    private const LEGACY_NOT_GIVEN_STATUSES = [
        'refused' => 'refused',
        'not_available' => 'medication_not_available',
        'hospitalized' => 'hospitalised',
        'self_administered' => 'self_administered',
    ];

    private function medicationAdministrationsInRange(array $filters)
    {
        return MedicationAdministration::whereRaw(
            'COALESCE(administered_at, created_at) BETWEEN ? AND ?',
            $this->utcRange($filters)
        )
            ->when($filters['branch_id'], fn ($q) => $q->whereHas(
                'medication.serviceUser',
                fn ($su) => $su->where('branch_id', $filters['branch_id'])
            ));
    }

    private function notGivenReason(MedicationAdministration $m): ?string
    {
        return $m->not_given_reason ?? self::LEGACY_NOT_GIVEN_STATUSES[$m->status] ?? null;
    }

    private function label(?string $value): string
    {
        return $value ? ucfirst(str_replace('_', ' ', $value)) : '—';
    }

    private function medicationAdminList(array $filters, ?string $status, string $title): array
    {
        $administrations = $this->medicationAdministrationsInRange($filters)
            // A "not given" record with the same reason counts too, e.g. refused.
            ->when($status, fn ($q) => $q->where(fn ($q) => $q->where('status', $status)
                ->orWhere(fn ($q) => $q->where('status', 'not_given')->where('not_given_reason', $status))))
            ->with(['medication.serviceUser', 'administeredBy'])
            ->orderByDesc('administered_at')
            ->get();

        return [
            'title' => $title,
            'columns' => [
                ['key' => 'when', 'label' => 'Date/Time'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'medication', 'label' => 'Medication'],
                ['key' => 'due', 'label' => 'Dose Due'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'reason', 'label' => 'Reason Not Given'],
                ['key' => 'stock_checked', 'label' => 'Stock Checked'],
                ['key' => 'administered_by', 'label' => 'Administered By'],
            ],
            'rows' => $administrations->map(fn (MedicationAdministration $m) => [
                'when' => ($m->administered_at ?? $m->created_at)->format('Y-m-d H:i'),
                'client' => $this->clientName($m->medication?->serviceUser),
                'medication' => $m->medication?->name ?? '—',
                'due' => $m->scheduled_time ?? '—',
                'status' => str_replace('_', ' ', $m->status),
                'reason' => $m->not_given_reason ? str_replace('_', ' ', $m->not_given_reason) : '—',
                'stock_checked' => $m->stock_checked === null ? '—' : ($m->stock_checked ? 'Yes' : 'No'),
                'administered_by' => $m->administeredBy->name ?? '—',
            ]),
        ];
    }

    /** Every dose recorded as not given, with its reason — including older statuses that carried one. */
    private function medicationNotGiven(array $filters): array
    {
        $administrations = $this->medicationAdministrationsInRange($filters)
            ->whereIn('status', ['not_given', ...array_keys(self::LEGACY_NOT_GIVEN_STATUSES)])
            ->with(['medication.serviceUser', 'administeredBy'])
            ->orderByDesc('administered_at')
            ->get();

        return [
            'title' => 'Medication Not Given',
            'columns' => [
                ['key' => 'when', 'label' => 'Date/Time'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'medication', 'label' => 'Medication'],
                ['key' => 'due', 'label' => 'Dose Due'],
                ['key' => 'reason', 'label' => 'Reason'],
                ['key' => 'recorded_by', 'label' => 'Recorded By'],
                ['key' => 'notes', 'label' => 'Notes'],
            ],
            'rows' => $administrations->map(fn (MedicationAdministration $m) => [
                'when' => ($m->administered_at ?? $m->created_at)->format('Y-m-d H:i'),
                'client' => $this->clientName($m->medication?->serviceUser),
                'medication' => $m->medication?->name ?? '—',
                'due' => $m->scheduled_time ?? '—',
                'reason' => $this->label($this->notGivenReason($m)),
                'recorded_by' => $m->administeredBy->name ?? '—',
                'notes' => $m->notes ?? '—',
            ]),
        ];
    }

    private function notGivenReasons(array $filters): array
    {
        $reasons = $this->medicationAdministrationsInRange($filters)
            ->whereIn('status', ['not_given', ...array_keys(self::LEGACY_NOT_GIVEN_STATUSES)])
            ->get()
            ->map(fn (MedicationAdministration $m) => $this->notGivenReason($m))
            ->filter();

        $total = $reasons->count();
        $counts = $reasons->countBy();

        return [
            'title' => 'Reasons Medication Not Given',
            'columns' => [
                ['key' => 'reason', 'label' => 'Reason'],
                ['key' => 'count', 'label' => 'Doses'],
                ['key' => 'percentage', 'label' => '% of Not Given'],
            ],
            'rows' => collect(MedicationAdministration::NOT_GIVEN_REASONS)->map(fn ($reason) => [
                'reason' => $this->label($reason),
                'count' => (int) ($counts[$reason] ?? 0),
                'percentage' => $total > 0 ? round((($counts[$reason] ?? 0) / $total) * 100, 1).'%' : '0%',
            ]),
        ];
    }

    /**
     * Scheduled doses whose time has passed with nothing recorded at all —
     * the gaps on the MAR chart. Unlike "missed", which needs someone to
     * have recorded the miss, these are the doses no one accounted for.
     *
     * Records made before doses had times (no scheduled_time) can't be tied
     * to a slot, so each one covers one otherwise-unmatched dose that day
     * rather than leaving the whole history looking unrecorded.
     */
    private function unrecordedDoses(array $filters): array
    {
        $timezone = TenantClock::timezoneFor($this->tenantId());
        $from = Carbon::parse($filters['from'], $timezone)->startOfDay();
        $to = Carbon::parse($filters['to'], $timezone)->endOfDay()->min(now($timezone));
        $rows = collect();

        if ($from->lte($to)) {
            $medications = Medication::where('status', 'active')
                ->whereNull('archived_at')
                ->where('is_prn', false)
                ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
                ->with([
                    'serviceUser',
                    'administrations' => fn ($q) => $q->whereBetween('administered_at', [$from->copy()->utc(), $to->copy()->utc()]),
                ])
                ->get();

            foreach ($medications as $medication) {
                $schedule = $medication->schedule ?? [];
                if ($schedule === []) {
                    continue;
                }

                $timed = $medication->administrations->whereNotNull('scheduled_time');
                $localDate = fn (MedicationAdministration $a) => $a->administered_at->copy()->setTimezone($timezone)->toDateString();
                $recorded = $timed
                    ->map(fn (MedicationAdministration $a) => $localDate($a).' '.$a->scheduled_time)
                    ->flip();
                $untimedPerDay = $medication->administrations->whereNull('scheduled_time')->countBy($localDate);

                $start = $medication->start_date ? Carbon::parse($medication->start_date->toDateString(), $timezone) : $from;
                $day = $from->copy()->max($start)->startOfDay();
                $lastDay = $medication->end_date ? $to->copy()->min(Carbon::parse($medication->end_date->toDateString(), $timezone)->endOfDay()) : $to;

                for (; $day->lte($lastDay); $day->addDay()) {
                    $untimed = $untimedPerDay[$day->toDateString()] ?? 0;
                    foreach ($schedule as $time) {
                        [$hour, $minute] = array_map('intval', explode(':', $time));
                        $due = $day->copy()->setTime($hour, $minute);
                        if ($due->gt($to) || $recorded->has($day->toDateString().' '.$time)) {
                            continue;
                        }
                        if ($untimed > 0) {
                            $untimed--;

                            continue;
                        }
                        $rows->push([
                            'due' => $due->format('Y-m-d H:i'),
                            'client' => $this->clientName($medication->serviceUser),
                            'medication' => trim("{$medication->name} {$medication->strength}"),
                            'dose' => $medication->dose,
                        ]);
                    }
                }
            }
        }

        return [
            'title' => 'Unrecorded Doses',
            'columns' => [
                ['key' => 'due', 'label' => 'Dose Due'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'medication', 'label' => 'Medication'],
                ['key' => 'dose', 'label' => 'Dose'],
            ],
            'rows' => $rows->sortBy('due')->values(),
        ];
    }

    /** Doses given further from their due time than the tenant's medication window allows. */
    private function lateMedication(array $filters): array
    {
        $window = (int) TenantSettings::for($this->tenantId(), 'medication_window_minutes');
        $rows = $this->medicationAdministrationsInRange($filters)
            ->whereIn('status', ['administered', 'prn'])
            ->whereNotNull('scheduled_time')
            ->whereNotNull('administered_at')
            ->with(['medication.serviceUser', 'administeredBy'])
            ->orderBy('administered_at')
            ->get()
            ->map(function (MedicationAdministration $m) {
                // Dose times are local wall-clock times; administered_at is UTC.
                $diff = $this->timeToMinutes($this->localTime($m->administered_at)) - $this->timeToMinutes($m->scheduled_time);

                return ['m' => $m, 'diff' => $diff];
            })
            ->filter(fn ($r) => abs($r['diff']) > $window)
            ->map(fn ($r) => [
                'date' => TenantClock::local($this->tenantId(), $r['m']->administered_at)->toDateString(),
                'client' => $this->clientName($r['m']->medication?->serviceUser),
                'medication' => $r['m']->medication?->name ?? '—',
                'due' => $r['m']->scheduled_time,
                'given' => $this->localTime($r['m']->administered_at),
                'timing' => ($r['diff'] > 0 ? 'Late by ' : 'Early by ').abs($r['diff']).' min',
                'administered_by' => $r['m']->administeredBy->name ?? '—',
            ])
            ->values();

        return [
            'title' => "Late Medication (more than {$window} minutes from due time)",
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'medication', 'label' => 'Medication'],
                ['key' => 'due', 'label' => 'Due'],
                ['key' => 'given', 'label' => 'Given'],
                ['key' => 'timing', 'label' => 'Timing'],
                ['key' => 'administered_by', 'label' => 'Administered By'],
            ],
            'rows' => $rows,
        ];
    }

    /**
     * How often carers confirmed the stock when recording a dose. Records
     * from before the stock check existed (null) are left out rather than
     * counted as unchecked.
     */
    private function stockChecks(array $filters): array
    {
        $byMedication = $this->medicationAdministrationsInRange($filters)
            ->whereNotNull('stock_checked')
            ->with('medication.serviceUser')
            ->get()
            ->groupBy('medication_id');

        return [
            'title' => 'Medication Stock Checks',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'medication', 'label' => 'Medication'],
                ['key' => 'recorded', 'label' => 'Doses Recorded'],
                ['key' => 'checked', 'label' => 'Stock Checked'],
                ['key' => 'percentage', 'label' => '% Checked'],
            ],
            'rows' => $byMedication->map(function ($records) {
                $medication = $records->first()->medication;
                $checked = $records->where('stock_checked', true)->count();

                return [
                    'client' => $this->clientName($medication?->serviceUser),
                    'medication' => $medication?->name ?? '—',
                    'recorded' => $records->count(),
                    'checked' => $checked,
                    'percentage' => round(($checked / $records->count()) * 100, 1).'%',
                ];
            })->sortBy('percentage', SORT_NATURAL)->values(),
        ];
    }

    // ---- Clinical / Observations ------------------------------------------

    private const NEWS2_TYPES = ['news2', 'respiratory_rate', 'oxygen_saturation', 'blood_pressure', 'pulse', 'temperature'];

    private const NEWS2_RISK_LABELS = [
        'none' => 'None',
        'low' => 'Low',
        'low_medium' => 'Low-medium (red score)',
        'medium' => 'Medium',
        'high' => 'High',
    ];

    /**
     * Each scored reading with its NEWS2 assessment. A full NEWS2 set gives
     * the aggregate score; a single vital sign gives that parameter's score.
     * Escalations keep only readings needing more than routine monitoring.
     */
    private function news2Scores(array $filters, bool $escalationsOnly): array
    {
        $observations = Observation::whereIn('type', self::NEWS2_TYPES)
            ->whereBetween('recorded_at', $this->utcRange($filters))
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with(['serviceUser', 'recordedBy'])
            ->orderByDesc('recorded_at')
            ->get();

        $rows = $observations
            ->map(fn (Observation $o) => ['o' => $o, 'a' => News2::assess($o->type, $o->value ?? [])])
            ->filter(fn ($r) => $r['a'] !== null && (! $escalationsOnly || ! in_array($r['a']['risk'], ['none', 'low'], true)))
            ->map(fn ($r) => [
                'when' => $r['o']->recorded_at->format('Y-m-d H:i'),
                'client' => $this->clientName($r['o']->serviceUser),
                'type' => $r['o']->type === 'news2' ? 'Full NEWS2 set' : $this->label($r['o']->type),
                'reading' => $this->formatObservationValue($r['o']),
                'score' => $r['a']['total'],
                'risk' => self::NEWS2_RISK_LABELS[$r['a']['risk']],
                'response' => $r['a']['response'],
                'recorded_by' => $r['o']->recordedBy->name ?? '—',
            ])
            ->values();

        return [
            'title' => $escalationsOnly ? 'NEWS2 Escalations' : 'NEWS2 Scores',
            'columns' => [
                ['key' => 'when', 'label' => 'Date/Time'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'type', 'label' => 'Reading'],
                ['key' => 'reading', 'label' => 'Value'],
                ['key' => 'score', 'label' => 'NEWS2 Score'],
                ['key' => 'risk', 'label' => 'Clinical Risk'],
                ['key' => 'response', 'label' => 'Response'],
                ['key' => 'recorded_by', 'label' => 'Recorded By'],
            ],
            'rows' => $rows,
        ];
    }

    private function observationTrend(array $filters, array $types, string $title): array
    {
        $observations = Observation::whereIn('type', $types)
            ->whereBetween('recorded_at', $this->utcRange($filters))
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with(['serviceUser', 'recordedBy'])
            ->orderBy('recorded_at')
            ->get();

        return [
            'title' => $title,
            'columns' => [
                ['key' => 'when', 'label' => 'Date/Time'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'value', 'label' => 'Value'],
                ['key' => 'score', 'label' => 'Score (0–3)'],
                ['key' => 'recorded_by', 'label' => 'Recorded By'],
            ],
            'rows' => $observations->map(fn (Observation $o) => [
                'when' => $o->recorded_at->format('Y-m-d H:i'),
                'client' => $this->clientName($o->serviceUser),
                'type' => str_replace('_', ' ', $o->type),
                'value' => $this->formatObservationValue($o),
                'score' => $this->highestParameterScore($o),
                'recorded_by' => $o->recordedBy->name ?? '—',
            ]),
        ];
    }

    /**
     * The worst 0–3 score across a reading's parameters — NEWS2 for the vital
     * signs it covers, RangeScores for diastolic BP and glucose — or "—" for
     * types neither scores (weight, pain…).
     */
    private function highestParameterScore(Observation $observation): int|string
    {
        $value = $observation->value ?? [];
        $scores = array_column([
            ...array_values(News2::parameterScores($observation->type, $value)),
            ...array_values(RangeScores::parameterScores($observation->type, $value)),
        ], 'score');

        return $scores === [] ? '—' : max($scores);
    }

    private function formatObservationValue(Observation $observation): string
    {
        $value = $observation->value ?? [];

        if ($observation->type === 'news2') {
            return sprintf(
                'RR %s · SpO₂ %s%% (%s) · BP %s · HR %s · %s · T %s°C',
                $value['respiration_rate'] ?? '—',
                $value['spo2'] ?? '—',
                ! empty($value['on_oxygen']) ? 'oxygen' : 'air',
                $value['systolic'] ?? '—',
                $value['pulse'] ?? '—',
                isset($value['consciousness']) ? strtoupper(substr((string) $value['consciousness'], 0, 1)) : '—',
                $value['temperature'] ?? '—',
            );
        }

        if (isset($value['reading']) && ! isset($value['value'])) {
            return (string) $value['reading'];
        }

        if ($observation->type === 'blood_pressure') {
            return isset($value['systolic'], $value['diastolic']) ? "{$value['systolic']}/{$value['diastolic']}" : '—';
        }

        if (isset($value['value'])) {
            return (string) $value['value'];
        }

        return $value ? json_encode($value) : '—';
    }

    private function clinicalAlerts(array $filters): array
    {
        $alerts = ClinicalAlert::whereBetween('created_at', $this->utcRange($filters))
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with('serviceUser')
            ->orderByDesc('created_at')
            ->get();

        return [
            'title' => 'Abnormal Observation Alerts',
            'columns' => [
                ['key' => 'when', 'label' => 'Date/Time'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'message', 'label' => 'Alert'],
                ['key' => 'severity', 'label' => 'Severity'],
                ['key' => 'acknowledged', 'label' => 'Acknowledged'],
            ],
            'rows' => $alerts->map(fn (ClinicalAlert $a) => [
                'when' => $a->created_at->format('Y-m-d H:i'),
                'client' => $this->clientName($a->serviceUser),
                'message' => $a->message,
                'severity' => str_replace('_', ' ', $a->severity),
                'acknowledged' => $a->acknowledged_at ? 'Yes' : 'No',
            ]),
        ];
    }

    // ---- Incidents & Safeguarding -----------------------------------------

    private function incidentList(array $filters, ?string $type, string $title): array
    {
        $incidents = $this->scopeByBranch(
            Incident::whereBetween('created_at', $this->utcRange($filters))
                ->when($type, fn ($q) => $q->where('type', $type)),
            $filters['branch_id']
        )
            ->with('serviceUser')
            ->orderByDesc('created_at')
            ->get();

        return [
            'title' => $title,
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'severity', 'label' => 'Severity'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $incidents->map(fn (Incident $i) => [
                'date' => $i->created_at->format('Y-m-d'),
                'client' => $i->serviceUser ? $this->clientName($i->serviceUser) : '—',
                'type' => str_replace('_', ' ', $i->type),
                'severity' => str_replace('_', ' ', $i->severity),
                'status' => str_replace('_', ' ', $i->status),
            ]),
        ];
    }

    private function safeguardingList(array $filters): array
    {
        $cases = SafeguardingCase::whereBetween('created_at', $this->utcRange($filters))
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with('serviceUser')
            ->orderByDesc('created_at')
            ->get();

        return [
            'title' => 'Safeguarding Concerns',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'concern_type', 'label' => 'Concern Type'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'immediate_risk', 'label' => 'Immediate Risk'],
            ],
            'rows' => $cases->map(fn (SafeguardingCase $c) => [
                'date' => $c->created_at->format('Y-m-d'),
                'client' => $c->serviceUser ? $this->clientName($c->serviceUser) : '—',
                'concern_type' => $c->concern_type ?? '—',
                'status' => str_replace('_', ' ', $c->status),
                'immediate_risk' => $c->immediate_risk ? 'Yes' : 'No',
            ]),
        ];
    }

    private function incidentBreakdown(array $filters, string $field, array $values, string $title): array
    {
        $base = $this->scopeByBranch(
            Incident::whereBetween('created_at', $this->utcRange($filters)),
            $filters['branch_id']
        );

        $total = (clone $base)->count();
        $counts = (clone $base)->selectRaw("{$field}, count(*) as c")->groupBy($field)->pluck('c', $field);

        return [
            'title' => $title,
            'columns' => [
                ['key' => 'value', 'label' => ucfirst($field)],
                ['key' => 'count', 'label' => 'Count'],
                ['key' => 'percentage', 'label' => '% of Total'],
            ],
            'rows' => collect($values)->map(fn ($v) => [
                'value' => str_replace('_', ' ', $v),
                'count' => (int) ($counts[$v] ?? 0),
                'percentage' => $total > 0 ? round((($counts[$v] ?? 0) / $total) * 100, 1).'%' : '0%',
            ]),
        ];
    }

    private function incidentFrequency(array $filters): array
    {
        $incidents = $this->scopeByBranch(
            Incident::whereBetween('created_at', $this->utcRange($filters))
                ->whereNotNull('service_user_id'),
            $filters['branch_id']
        )->with('serviceUser')->get();

        $rows = $incidents->groupBy('service_user_id')
            ->map(fn ($group) => [
                'client' => $this->clientName($group->first()->serviceUser),
                'incidents' => $group->count(),
            ])
            ->sortByDesc('incidents')
            ->values();

        return [
            'title' => 'Incident Frequency per Client',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'incidents', 'label' => 'Incident Count'],
            ],
            'rows' => $rows,
        ];
    }

    private function correctiveActions(array $filters): array
    {
        $incidents = $this->scopeByBranch(
            Incident::whereBetween('created_at', $this->utcRange($filters))
                ->whereNotNull('corrective_actions'),
            $filters['branch_id']
        )->with('serviceUser')->orderByDesc('created_at')->get();

        return [
            'title' => 'Corrective Actions',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'type', 'label' => 'Incident Type'],
                ['key' => 'corrective_actions', 'label' => 'Corrective Actions'],
            ],
            'rows' => $incidents->map(fn (Incident $i) => [
                'date' => $i->created_at->format('Y-m-d'),
                'client' => $i->serviceUser ? $this->clientName($i->serviceUser) : '—',
                'type' => str_replace('_', ' ', $i->type),
                'corrective_actions' => $i->corrective_actions,
            ]),
        ];
    }

    private function unresolvedActions(array $filters): array
    {
        $openIncidents = $this->scopeByBranch(
            Incident::whereBetween('created_at', $this->utcRange($filters))
                ->where('status', '!=', 'closed'),
            $filters['branch_id']
        )->with('serviceUser')->get()->map(fn (Incident $i) => [
            'date' => $i->created_at->format('Y-m-d'),
            'source' => 'Incident',
            'client' => $i->serviceUser ? $this->clientName($i->serviceUser) : '—',
            'description' => str_replace('_', ' ', $i->type),
            'status' => str_replace('_', ' ', $i->status),
        ]);

        $openSafeguarding = SafeguardingCase::whereBetween('created_at', $this->utcRange($filters))
            ->where('status', '!=', 'closed')
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with('serviceUser')->get()->map(fn (SafeguardingCase $c) => [
                'date' => $c->created_at->format('Y-m-d'),
                'source' => 'Safeguarding',
                'client' => $c->serviceUser ? $this->clientName($c->serviceUser) : '—',
                'description' => $c->concern_type ?? 'Safeguarding concern',
                'status' => str_replace('_', ' ', $c->status),
            ]);

        $rows = $openIncidents->concat($openSafeguarding)->sortByDesc('date')->values();

        return [
            'title' => 'Unresolved Actions',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'source', 'label' => 'Source'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'description', 'label' => 'Description'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $rows,
        ];
    }

    // ---- Staff & Workforce --------------------------------------------------

    private function leaveList(array $filters): array
    {
        $leave = LeaveRequest::whereBetween('start_date', [$filters['from'], $filters['to']])
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('user.staffProfile', fn ($sp) => $sp->where('branch_id', $filters['branch_id'])))
            ->with('user')
            ->orderBy('start_date')
            ->get();

        return [
            'title' => 'Leave Report',
            'columns' => [
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'start_date', 'label' => 'Start'],
                ['key' => 'end_date', 'label' => 'End'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $leave->map(fn (LeaveRequest $l) => [
                'staff' => $l->user->name ?? '—',
                'type' => str_replace('_', ' ', $l->type),
                'start_date' => $l->start_date->toDateString(),
                'end_date' => $l->end_date->toDateString(),
                'status' => str_replace('_', ' ', $l->status),
            ]),
        ];
    }

    private function shiftList(array $filters): array
    {
        $shifts = Shift::whereBetween('shift_date', [$filters['from'], $filters['to']])
            ->when($filters['branch_id'], fn ($q) => $q->where('branch_id', $filters['branch_id']))
            ->with('user')
            ->orderBy('shift_date')
            ->get();

        return [
            'title' => 'Shift Coverage',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'time', 'label' => 'Time'],
                ['key' => 'type', 'label' => 'Type'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $shifts->map(fn (Shift $s) => [
                'date' => $s->shift_date->toDateString(),
                'staff' => $s->user->name ?? '—',
                'time' => "{$s->start_time}–{$s->end_time}",
                'type' => str_replace('_', ' ', $s->shift_type),
                'status' => str_replace('_', ' ', $s->status),
            ]),
        ];
    }

    private function workedHours(array $filters): array
    {
        $visits = $this->scopeByBranch(
            Visit::where('status', 'completed')
                ->whereBetween('visit_date', [$filters['from'], $filters['to']])
                ->whereNotNull('carer_id'),
            $filters['branch_id']
        )->with('carer')->get();

        $rows = $visits->groupBy('carer_id')
            ->map(function ($group) {
                $hours = $group->sum(fn (Visit $v) => max(0, $this->timeToMinutes($v->end_time) - $this->timeToMinutes($v->start_time)) / 60);

                return [
                    'staff' => $group->first()->carer->name ?? '—',
                    'visits' => $group->count(),
                    'hours' => round($hours, 2),
                ];
            })
            ->sortByDesc('hours')
            ->values();

        return [
            'title' => 'Worked Hours (from completed visits)',
            'columns' => [
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'visits', 'label' => 'Visits Completed'],
                ['key' => 'hours', 'label' => 'Hours'],
            ],
            'rows' => $rows,
        ];
    }

    // ---- Training & Compliance ------------------------------------------------

    private function trainingList(array $filters, string $statusFilter, string $title): array
    {
        $warningDays = (int) TenantSettings::for($this->tenantId(), 'training_expiry_warning_days');
        $records = TrainingRecord::query()
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('user.staffProfile', fn ($sp) => $sp->where('branch_id', $filters['branch_id'])))
            ->with(['user', 'trainingCourse'])
            ->get()
            ->filter(function (TrainingRecord $r) use ($statusFilter, $warningDays) {
                if (! $r->expiry_date) {
                    return false;
                }
                $isExpired = $r->expiry_date->isPast();
                $isExpiringSoon = ! $isExpired && $r->expiry_date->lte(now()->addDays($warningDays));

                return $statusFilter === 'expired' ? $isExpired : $isExpiringSoon;
            })
            ->sortBy(fn (TrainingRecord $r) => $r->expiry_date)
            ->values();

        return [
            'title' => $title,
            'columns' => [
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'course', 'label' => 'Course'],
                ['key' => 'expiry_date', 'label' => 'Expiry Date'],
            ],
            'rows' => $records->map(fn (TrainingRecord $r) => [
                'staff' => $r->user->name ?? '—',
                'course' => $r->trainingCourse->name ?? '—',
                'expiry_date' => $r->expiry_date->toDateString(),
            ]),
        ];
    }

    private function mandatoryTrainingCompletion(array $filters): array
    {
        $records = TrainingRecord::whereHas('trainingCourse', fn ($q) => $q->where('is_mandatory', true))
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('user.staffProfile', fn ($sp) => $sp->where('branch_id', $filters['branch_id'])))
            ->with('trainingCourse')
            ->get();

        $rows = $records->groupBy(fn (TrainingRecord $r) => $r->trainingCourse->name ?? 'Unknown course')
            ->map(function ($group, $course) {
                $completed = $group->filter(fn (TrainingRecord $r) => ! $r->expiry_date || $r->expiry_date->isFuture())->count();

                return [
                    'course' => $course,
                    'staff_recorded' => $group->count(),
                    'current' => $completed,
                    'completion_rate' => $group->count() > 0 ? round(($completed / $group->count()) * 100, 1).'%' : '0%',
                ];
            })
            ->values();

        return [
            'title' => 'Mandatory Training Completion',
            'columns' => [
                ['key' => 'course', 'label' => 'Course'],
                ['key' => 'staff_recorded', 'label' => 'Staff with a Record'],
                ['key' => 'current', 'label' => 'Currently Valid'],
                ['key' => 'completion_rate', 'label' => 'Completion Rate'],
            ],
            'rows' => $rows,
        ];
    }

    private function trainingComplianceByBranch(array $filters): array
    {
        $records = TrainingRecord::when($filters['branch_id'], fn ($q) => $q->whereHas('user.staffProfile', fn ($sp) => $sp->where('branch_id', $filters['branch_id'])))
            ->with('user.staffProfile.branch')
            ->get();

        $rows = $records->groupBy(fn (TrainingRecord $r) => $r->user->staffProfile?->branch?->name ?? 'No branch')
            ->map(function ($group, $branchName) {
                $valid = $group->filter(fn (TrainingRecord $r) => ! $r->expiry_date || $r->expiry_date->isFuture())->count();

                return [
                    'branch' => $branchName,
                    'records' => $group->count(),
                    'valid' => $valid,
                    'compliance_pct' => $group->count() > 0 ? round(($valid / $group->count()) * 100, 1).'%' : '0%',
                ];
            })
            ->values();

        return [
            'title' => 'Training Compliance % by Branch',
            'columns' => [
                ['key' => 'branch', 'label' => 'Branch'],
                ['key' => 'records', 'label' => 'Training Records'],
                ['key' => 'valid', 'label' => 'Currently Valid'],
                ['key' => 'compliance_pct', 'label' => 'Compliance %'],
            ],
            'rows' => $rows,
        ];
    }

    private function staffDocumentExpiry(array $filters): array
    {
        $staff = StaffProfile::whereHas('documents', fn ($q) => $q->whereNotNull('expiry_date'))
            ->when($filters['branch_id'], fn ($q) => $q->where('branch_id', $filters['branch_id']))
            ->with(['user', 'documents' => fn ($q) => $q->whereNotNull('expiry_date')])
            ->get();

        $rows = $staff->flatMap(fn (StaffProfile $sp) => $sp->documents->map(fn (Document $d) => [
            'staff' => $sp->user->name ?? '—',
            'document' => $d->original_filename,
            'category' => $d->category ?? '—',
            'expiry_date' => $d->expiry_date->toDateString(),
        ]))->sortBy('expiry_date')->values();

        return [
            'title' => 'Staff Document Expiry',
            'columns' => [
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'document', 'label' => 'Document'],
                ['key' => 'category', 'label' => 'Category'],
                ['key' => 'expiry_date', 'label' => 'Expiry Date'],
            ],
            'rows' => $rows,
        ];
    }

    // ---- Rostering --------------------------------------------------------

    private function doubleBookings(array $filters): array
    {
        $visits = $this->scopeByBranch(
            Visit::whereBetween('visit_date', [$filters['from'], $filters['to']])->whereNotNull('carer_id'),
            $filters['branch_id']
        )->with('carer')->get();

        $conflicts = [];
        foreach ($visits->groupBy(fn (Visit $v) => $v->carer_id.'|'.$v->visit_date->toDateString()) as $group) {
            $list = $group->values();
            for ($i = 0; $i < $list->count(); $i++) {
                for ($j = $i + 1; $j < $list->count(); $j++) {
                    $a = $list[$i];
                    $b = $list[$j];
                    $overlap = $this->timeToMinutes($a->start_time) < $this->timeToMinutes($b->end_time)
                        && $this->timeToMinutes($a->end_time) > $this->timeToMinutes($b->start_time);
                    if ($overlap) {
                        $conflicts[] = [
                            'date' => $a->visit_date->toDateString(),
                            'staff' => $a->carer->name ?? '—',
                            'visit_a' => "{$a->start_time}–{$a->end_time}",
                            'visit_b' => "{$b->start_time}–{$b->end_time}",
                        ];
                    }
                }
            }
        }

        return [
            'title' => 'Double-Bookings',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'visit_a', 'label' => 'Visit A'],
                ['key' => 'visit_b', 'label' => 'Visit B'],
            ],
            'rows' => collect($conflicts),
        ];
    }

    // ---- Finance ------------------------------------------------------------

    private function invoiceList(array $filters, ?string $statusGroup, string $title): array
    {
        $statuses = match ($statusGroup) {
            'outstanding' => ['sent', 'overdue'],
            'paid' => ['paid'],
            default => null,
        };

        $invoices = $this->scopeByBranch(
            Invoice::whereBetween('issue_date', [$filters['from'], $filters['to']])
                ->when($statuses, fn ($q) => $q->whereIn('status', $statuses)),
            $filters['branch_id']
        )->with('serviceUser')->orderByDesc('issue_date')->get();

        return [
            'title' => $title,
            'columns' => [
                ['key' => 'invoice_number', 'label' => 'Invoice #'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'issue_date', 'label' => 'Issue Date'],
                ['key' => 'total', 'label' => 'Total'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            'rows' => $invoices->map(fn (Invoice $i) => [
                'invoice_number' => $i->invoice_number ?? "#{$i->id}",
                'client' => $this->clientName($i->serviceUser),
                'issue_date' => $i->issue_date?->toDateString() ?? '—',
                'total' => number_format((float) $i->total, 2)." {$i->currency}",
                'status' => str_replace('_', ' ', $i->status),
            ]),
        ];
    }

    private function revenueBreakdown(array $filters): array
    {
        $invoices = Invoice::whereIn('status', self::BILLED_STATUSES)
            ->whereBetween('issue_date', [$filters['from'], $filters['to']])
            ->with('serviceUser.branch')
            ->get();

        $rows = $invoices->groupBy(fn (Invoice $i) => $i->serviceUser?->branch?->name ?? 'No branch')
            ->when($filters['branch_id'], fn ($grouped) => $grouped->filter(
                fn ($group) => $group->first()->serviceUser?->branch_id === $filters['branch_id']
            ))
            ->map(fn ($group, $branchName) => [
                'branch' => $branchName,
                'invoices' => $group->count(),
                'revenue' => number_format((float) $group->sum('total'), 2),
            ])
            ->values();

        return [
            'title' => 'Revenue by Branch',
            'columns' => [
                ['key' => 'branch', 'label' => 'Branch'],
                ['key' => 'invoices', 'label' => 'Invoices'],
                ['key' => 'revenue', 'label' => 'Revenue'],
            ],
            'rows' => $rows,
        ];
    }

    private function careHoursBilled(array $filters): array
    {
        $invoices = Invoice::whereIn('status', self::BILLED_STATUSES)
            ->whereBetween('issue_date', [$filters['from'], $filters['to']])
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with(['serviceUser', 'lineItems'])
            ->get();

        return [
            'title' => 'Care Hours Billed',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'invoice_number', 'label' => 'Invoice #'],
                ['key' => 'quantity', 'label' => 'Billed Quantity'],
                ['key' => 'amount', 'label' => 'Amount'],
            ],
            'rows' => $invoices->flatMap(fn (Invoice $i) => $i->lineItems->map(fn ($li) => [
                'client' => $this->clientName($i->serviceUser),
                'invoice_number' => $i->invoice_number ?? "#{$i->id}",
                'quantity' => (float) $li->quantity,
                'amount' => number_format((float) $li->amount, 2),
            ])),
        ];
    }

    private function payrollCost(array $filters): array
    {
        $payslips = Payslip::whereHas('payPeriod', fn ($q) => $q->where('start_date', '<=', $filters['to'])->where('end_date', '>=', $filters['from']))
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('user.staffProfile', fn ($sp) => $sp->where('branch_id', $filters['branch_id'])))
            ->with(['user', 'payPeriod'])
            ->get();

        return [
            'title' => 'Payroll Cost',
            'columns' => [
                ['key' => 'staff', 'label' => 'Staff'],
                ['key' => 'pay_period', 'label' => 'Pay Period'],
                ['key' => 'hours', 'label' => 'Hours'],
                ['key' => 'gross_pay', 'label' => 'Gross Pay'],
                ['key' => 'net_pay', 'label' => 'Net Pay'],
            ],
            'rows' => $payslips->map(fn (Payslip $p) => [
                'staff' => $p->user->name ?? '—',
                'pay_period' => "{$p->payPeriod->start_date->toDateString()} to {$p->payPeriod->end_date->toDateString()}",
                'hours' => (float) $p->regular_hours,
                'gross_pay' => number_format((float) $p->gross_pay, 2),
                'net_pay' => number_format((float) $p->net_pay, 2),
            ]),
        ];
    }

    // ---- Quality & Audit ----------------------------------------------------

    private function overdueReviews(array $filters): array
    {
        $overdue = fn ($query) => $query->whereNotNull('review_date')
            ->whereDate('review_date', '<', now())
            ->whereHas('carePlan', fn ($q) => $q->where('status', 'active'))
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('carePlan.serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with('carePlan.serviceUser')
            ->get();

        $sections = $overdue(CarePlanSection::query())->map(fn (CarePlanSection $s) => [
            'client' => $this->clientName($s->carePlan?->serviceUser),
            'area' => str_replace('_', ' ', $s->area),
            'review_date' => $s->review_date->toDateString(),
        ]);
        $risks = $overdue(CarePlanRiskAssessment::query())->map(fn (CarePlanRiskAssessment $ra) => [
            'client' => $this->clientName($ra->carePlan?->serviceUser),
            'area' => "Risk: {$ra->hazard}",
            'review_date' => $ra->review_date->toDateString(),
        ]);

        return [
            'title' => 'Care Plan Reviews Overdue',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'area', 'label' => 'Care Area / Risk'],
                ['key' => 'review_date', 'label' => 'Review Was Due'],
            ],
            'rows' => $sections->concat($risks)->sortBy('review_date')->values(),
        ];
    }

    /** Active care plans in scope — the current position, not a date range. */
    private function activeCarePlans(array $filters)
    {
        return CarePlan::where('status', 'active')
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with(['serviceUser', 'sections', 'riskAssessments.actionOwner'])
            ->get()
            ->sortBy(fn (CarePlan $p) => $this->clientName($p->serviceUser))
            ->values();
    }

    private function riskRating(?int $score): string
    {
        return match (true) {
            ! $score => 'Not scored',
            $score <= 4 => "Low ({$score})",
            $score <= 9 => "Medium ({$score})",
            $score <= 16 => "High ({$score})",
            default => "Very high ({$score})",
        };
    }

    /** A risk's current score — residual once controls are scored, otherwise initial. */
    private function currentRiskScore(CarePlanRiskAssessment $ra): ?int
    {
        return $ra->residualRiskScore() ?? $ra->riskScore();
    }

    private function carePlanSummary(array $filters): array
    {
        return [
            'title' => 'Care Plan Summary',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'version', 'label' => 'Version'],
                ['key' => 'effective_from', 'label' => 'Effective From'],
                ['key' => 'sections', 'label' => 'Care Areas'],
                ['key' => 'risks', 'label' => 'Risks'],
                ['key' => 'high_risks', 'label' => 'High Risks'],
                ['key' => 'home_care_plan', 'label' => 'Home Care Plan'],
                ['key' => 'next_review', 'label' => 'Next Review'],
            ],
            'rows' => $this->activeCarePlans($filters)->map(function (CarePlan $plan) {
                $reviews = $plan->sections->pluck('review_date')
                    ->concat($plan->riskAssessments->pluck('review_date'))
                    ->filter()
                    ->sort();

                return [
                    'client' => $this->clientName($plan->serviceUser),
                    'version' => $plan->version,
                    'effective_from' => $plan->effective_from?->toDateString() ?? '—',
                    'sections' => $plan->sections->count(),
                    'risks' => $plan->riskAssessments->count(),
                    'high_risks' => $plan->riskAssessments->filter(fn ($ra) => ($this->currentRiskScore($ra) ?? 0) >= 10)->count(),
                    'home_care_plan' => HomeCarePlan::normalize($plan->home_care_plan) ? 'Yes' : 'Not started',
                    'next_review' => $reviews->first()?->toDateString() ?? '—',
                ];
            }),
        ];
    }

    /**
     * How complete each active plan's Home Care Plan is: the narrative
     * fields, needs per area, consent per area, the summaries, and whether
     * any risk has been assessed. Lowest first, so the gaps lead.
     */
    private function documentationCompleteness(array $filters): array
    {
        $needCount = count(HomeCarePlan::NEED_AREAS);
        $consentCount = count(HomeCarePlan::CONSENT_AREAS);
        $summaryCount = count(HomeCarePlan::SUMMARIES);

        $rows = $this->activeCarePlans($filters)->map(function (CarePlan $plan) use ($needCount, $consentCount, $summaryCount) {
            $hcp = $plan->home_care_plan ?? [];
            $needs = collect($hcp['needs'] ?? []);
            $needsRecorded = $needs->filter(fn ($n) => ! empty($n['details']))->count();
            $consentsRecorded = collect(HomeCarePlan::CONSENT_AREAS)->filter(fn ($a) => isset($needs[$a]['consented']))->count();
            $summaries = count(array_filter($hcp['summaries'] ?? []));

            $checks = [
                'About me' => ! empty($hcp['about_me']),
                'Goals and outcomes' => ! empty($hcp['desired_outcomes']) || ! empty($hcp['goals_and_outcomes']),
                'Cognitive impairment' => ! empty($hcp['cognitive_impairment_summary']) || ! empty($hcp['cognitive_impairment']),
                'Risk assessment' => $plan->riskAssessments->isNotEmpty(),
            ];
            $score = (count(array_filter($checks)) + $needsRecorded / $needCount + $consentsRecorded / $consentCount + $summaries / $summaryCount)
                / (count($checks) + 3);

            $missing = array_keys(array_filter($checks, fn ($done) => ! $done));
            if ($consentsRecorded < $consentCount) {
                $missing[] = ($consentCount - $consentsRecorded).' consent(s)';
            }

            return [
                'client' => $this->clientName($plan->serviceUser),
                'version' => $plan->version,
                'completeness' => round($score * 100).'%',
                'needs' => "{$needsRecorded}/{$needCount}",
                'consents' => "{$consentsRecorded}/{$consentCount}",
                'summaries' => "{$summaries}/{$summaryCount}",
                'missing' => $missing === [] ? '—' : implode(', ', $missing),
                'sort' => $score,
            ];
        });

        return [
            'title' => 'Documentation Completeness',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'version', 'label' => 'Plan Version'],
                ['key' => 'completeness', 'label' => 'Complete'],
                ['key' => 'needs', 'label' => 'Needs Recorded'],
                ['key' => 'consents', 'label' => 'Consent Recorded'],
                ['key' => 'summaries', 'label' => 'Summaries'],
                ['key' => 'missing', 'label' => 'Missing'],
            ],
            'rows' => $rows->sortBy('sort')->map(fn ($r) => collect($r)->except('sort')->all())->values(),
        ];
    }

    /** The client's consent to each area of support, from their Home Care Plan. */
    private function careConsent(array $filters): array
    {
        $rows = $this->activeCarePlans($filters)->flatMap(function (CarePlan $plan) {
            $needs = $plan->home_care_plan['needs'] ?? [];

            return collect(HomeCarePlan::CONSENT_AREAS)->map(fn ($area) => [
                'client' => $this->clientName($plan->serviceUser),
                'area' => HomeCarePlan::AREA_LABELS[$area],
                'consent' => match ($needs[$area]['consented'] ?? null) {
                    true => 'Given',
                    false => 'Not given',
                    default => 'Not recorded',
                },
                'needs_recorded' => empty($needs[$area]['details']) ? 'No' : 'Yes',
            ]);
        });

        return [
            'title' => 'Consent to Care',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'area', 'label' => 'Area of Support'],
                ['key' => 'consent', 'label' => 'Consent'],
                ['key' => 'needs_recorded', 'label' => 'Needs Recorded'],
            ],
            'rows' => $rows->values(),
        ];
    }

    /** Every risk on an active care plan, highest current risk first. */
    private function riskRegister(array $filters): array
    {
        $rows = $this->activeCarePlans($filters)->flatMap(fn (CarePlan $plan) => $plan->riskAssessments->map(fn (CarePlanRiskAssessment $ra) => [
            'client' => $this->clientName($plan->serviceUser),
            'risk_type' => $ra->type === 'medication' ? 'Medication' : $this->label($ra->risk_type),
            'risk' => $ra->hazard,
            'initial' => $this->riskRating($ra->riskScore()),
            'residual' => $this->riskRating($ra->residualRiskScore()),
            'target' => $this->riskRating($ra->targetRiskScore()),
            'contingency' => $ra->contingency_plan_required ? ($ra->contingency_plan ? 'Required — in place' : 'Required — not written') : 'Not required',
            'action_by' => $ra->actionOwner->name ?? '—',
            'review_date' => $ra->review_date?->toDateString() ?? '—',
            'sort' => $this->currentRiskScore($ra) ?? 0,
        ]));

        return [
            'title' => 'Risk Register',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'risk_type', 'label' => 'Risk Type'],
                ['key' => 'risk', 'label' => 'Risk'],
                ['key' => 'initial', 'label' => 'Initial'],
                ['key' => 'residual', 'label' => 'Residual'],
                ['key' => 'target', 'label' => 'Target'],
                ['key' => 'contingency', 'label' => 'Contingency Plan'],
                ['key' => 'action_by', 'label' => 'Action By'],
                ['key' => 'review_date', 'label' => 'Review'],
            ],
            'rows' => $rows->sortByDesc('sort')->map(fn ($r) => collect($r)->except('sort')->all())->values(),
        ];
    }

    // ---- GPS / Visit Verification ----------------------------------------

    /**
     * Every scheduled visit in range, GPS-checked against the client's
     * address — not just the ones with a check-in (unlike checkinList()
     * below), so a carer who never showed up at all shows up as clearly as
     * one who did but outside the geofence. This is the "did they actually
     * go" report: Trip Verified is the single column that answers it,
     * everything else is the evidence for that verdict.
     */
    private function tripsActivity(array $filters): array
    {
        $visits = Visit::whereBetween('visit_date', [$filters['from'], $filters['to']])
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with(['serviceUser', 'carer'])
            ->orderBy('visit_date')->orderBy('start_time')
            ->get();

        // Cached per tenant so a platform-wide run of this report doesn't
        // re-fetch the same tenant's setting for every row.
        $radiusByTenant = [];
        $geofenceRadius = function (int $tenantId) use (&$radiusByTenant) {
            return $radiusByTenant[$tenantId] ??= (int) (Tenant::find($tenantId)?->setting('geofence_radius_meters') ?? 100);
        };

        $rows = $visits->map(function (Visit $v) use ($geofenceRadius) {
            $checkInDistance = ($v->check_in_lat !== null && $v->serviceUser?->latitude)
                ? Haversine::distanceInMeters(
                    (float) $v->check_in_lat, (float) $v->check_in_lng,
                    (float) $v->serviceUser->latitude, (float) $v->serviceUser->longitude,
                )
                : null;
            $checkOutDistance = ($v->check_out_lat !== null && $v->serviceUser?->latitude)
                ? Haversine::distanceInMeters(
                    (float) $v->check_out_lat, (float) $v->check_out_lng,
                    (float) $v->serviceUser->latitude, (float) $v->serviceUser->longitude,
                )
                : null;

            $tripVerified = match (true) {
                ! $v->check_in_at => 'No check-in',
                (bool) $v->override_reason => 'Overridden',
                $checkInDistance === null => 'Unverifiable — no client address on file',
                $checkInDistance <= $geofenceRadius($v->tenant_id) => 'Verified',
                default => 'Out of range',
            };

            return [
                'date' => $v->visit_date->toDateString(),
                'client' => $this->clientName($v->serviceUser),
                'carer' => $v->carer->name ?? 'Unassigned',
                'scheduled' => "{$v->start_time}–{$v->end_time}",
                'status' => str_replace('_', ' ', $v->status),
                'check_in' => $this->localTime($v->check_in_at),
                'check_in_distance_m' => $checkInDistance !== null ? round($checkInDistance) : '—',
                'check_out' => $this->localTime($v->check_out_at),
                'check_out_distance_m' => $checkOutDistance !== null ? round($checkOutDistance) : '—',
                'trip_verified' => $tripVerified,
                'override_reason' => $v->override_reason ?? '—',
            ];
        });

        return [
            'title' => 'Trips Activity Report',
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'carer', 'label' => 'Carer'],
                ['key' => 'scheduled', 'label' => 'Scheduled'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'check_in', 'label' => 'Check-In'],
                ['key' => 'check_in_distance_m', 'label' => 'Check-In Distance (m)'],
                ['key' => 'check_out', 'label' => 'Check-Out'],
                ['key' => 'check_out_distance_m', 'label' => 'Check-Out Distance (m)'],
                ['key' => 'trip_verified', 'label' => 'Trip Verified'],
                ['key' => 'override_reason', 'label' => 'Override Reason'],
            ],
            'rows' => $rows,
        ];
    }

    private function checkinList(array $filters, string $mode): array
    {
        $suspiciousDistanceMeters = 500;

        $visits = Visit::whereBetween('visit_date', [$filters['from'], $filters['to']])
            ->whereNotNull('check_in_at')
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with(['serviceUser', 'carer'])
            ->get();

        $rows = $visits->map(function (Visit $v) {
            $distance = ($v->check_in_lat && $v->serviceUser?->latitude)
                ? Haversine::distanceInMeters(
                    (float) $v->check_in_lat, (float) $v->check_in_lng,
                    (float) $v->serviceUser->latitude, (float) $v->serviceUser->longitude
                )
                : null;

            return [$v, $distance];
        });

        $filtered = match ($mode) {
            'verified' => $rows->filter(fn ($p) => ! $p[0]->override_reason),
            'overrides' => $rows->filter(fn ($p) => $p[0]->override_reason),
            'suspicious' => $rows->filter(fn ($p) => $p[0]->override_reason || ($p[1] !== null && $p[1] > $suspiciousDistanceMeters)),
            default => $rows->sortByDesc(fn ($p) => $p[1] ?? 0),
        };

        $titles = [
            'verified' => 'Verified Check-Ins',
            'overrides' => 'Manual Check-In Overrides',
            'suspicious' => 'Suspicious Check-Ins',
            'distance' => 'Check-In Distance from Client',
        ];

        return [
            'title' => $titles[$mode],
            'columns' => [
                ['key' => 'date', 'label' => 'Date'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'carer', 'label' => 'Carer'],
                ['key' => 'distance_m', 'label' => 'Distance (m)'],
                ['key' => 'override_reason', 'label' => 'Override Reason'],
            ],
            'rows' => $filtered->map(fn ($p) => [
                'date' => $p[0]->visit_date->toDateString(),
                'client' => $this->clientName($p[0]->serviceUser),
                'carer' => $p[0]->carer->name ?? 'Unassigned',
                'distance_m' => $p[1] !== null ? round($p[1]) : '—',
                'override_reason' => $p[0]->override_reason ?? '—',
            ])->values(),
        ];
    }

    // ---- Management ---------------------------------------------------------

    private function branchPerformance(array $filters): array
    {
        $branches = Branch::all();

        $rows = $branches->map(function (Branch $branch) use ($filters) {
            $visits = Visit::whereBetween('visit_date', [$filters['from'], $filters['to']])
                ->whereHas('serviceUser', fn ($q) => $q->where('branch_id', $branch->id));

            $incidents = Incident::whereBetween('created_at', $this->utcRange($filters))
                ->whereHas('serviceUser', fn ($q) => $q->where('branch_id', $branch->id))
                ->count();

            $activeClients = ServiceUser::where('branch_id', $branch->id)->where('status', 'active')->count();

            return [
                'branch' => $branch->name,
                'active_clients' => $activeClients,
                'visits' => (clone $visits)->count(),
                'missed_visits' => (clone $visits)->where('status', 'missed')->count(),
                'incidents' => $incidents,
            ];
        });

        return [
            'title' => 'Branch Performance',
            'columns' => [
                ['key' => 'branch', 'label' => 'Branch'],
                ['key' => 'active_clients', 'label' => 'Active Clients'],
                ['key' => 'visits', 'label' => 'Visits'],
                ['key' => 'missed_visits', 'label' => 'Missed Visits'],
                ['key' => 'incidents', 'label' => 'Incidents'],
            ],
            'rows' => $rows,
        ];
    }
}
