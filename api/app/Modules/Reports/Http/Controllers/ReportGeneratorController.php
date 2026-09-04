<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Invoice;
use App\Modules\CarePlanning\Models\CarePlanSection;
use App\Modules\Documents\Models\Document;
use App\Modules\Hr\Models\LeaveRequest;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Medications\Models\MedicationAdministration;
use App\Modules\Observations\Models\ClinicalAlert;
use App\Modules\Observations\Models\Observation;
use App\Modules\Organization\Models\Branch;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Reports\Support\ReportRoles;
use App\Modules\Rostering\Models\Shift;
use App\Modules\Safeguarding\Models\SafeguardingCase;
use App\Modules\ServiceUsers\Models\ServiceUser;
use App\Modules\Staff\Models\StaffProfile;
use App\Modules\Training\Models\TrainingRecord;
use App\Modules\Visits\Models\Visit;
use App\Support\Geo\Haversine;
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
            'from' => $validated['from'] ?? now()->startOfMonth()->toDateString(),
            'to' => $validated['to'] ?? now()->toDateString(),
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
        return match ($key) {
            // Client / Service User
            'visit_history' => $this->visitList($filters, null, 'Visit History'),
            'missed_visits' => $this->visitList($filters, 'missed', 'Missed Visits'),
            'active_clients' => $this->clientsByStatus($filters, 'active', 'Active Clients'),
            'discharged_clients' => $this->clientsByStatus($filters, 'discharged', 'Discharged Clients'),

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

            // GPS / Visit Verification
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
                ['key' => 'funding_source', 'label' => 'Funding'],
                ['key' => 'address', 'label' => 'Address'],
            ],
            'rows' => $clients->map(fn (ServiceUser $su) => [
                'name' => $this->clientName($su),
                'funding_source' => $su->funding_source ?? '—',
                'address' => $su->address ?? '—',
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
                $scheduledStart = Carbon::parse($v->visit_date->toDateString().' '.$v->start_time);
                $minutesLate = (int) round(($v->check_in_at->timestamp - $scheduledStart->timestamp) / 60);

                return [$v, $minutesLate];
            })
            ->filter(fn ($pair) => $pair[1] > 10)
            ->map(fn ($pair) => [
                'date' => $pair[0]->visit_date->toDateString(),
                'client' => $this->clientName($pair[0]->serviceUser),
                'carer' => $pair[0]->carer->name ?? 'Unassigned',
                'scheduled' => $pair[0]->start_time,
                'checked_in' => $pair[0]->check_in_at->format('H:i'),
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

    private function medicationAdminList(array $filters, ?string $status, string $title): array
    {
        $administrations = MedicationAdministration::whereRaw(
            'COALESCE(administered_at, created_at) BETWEEN ? AND ?',
            ["{$filters['from']} 00:00:00", "{$filters['to']} 23:59:59"]
        )
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($filters['branch_id'], fn ($q) => $q->whereHas(
                'medication.serviceUser',
                fn ($su) => $su->where('branch_id', $filters['branch_id'])
            ))
            ->with(['medication.serviceUser', 'administeredBy'])
            ->orderByDesc('administered_at')
            ->get();

        return [
            'title' => $title,
            'columns' => [
                ['key' => 'when', 'label' => 'Date/Time'],
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'medication', 'label' => 'Medication'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'administered_by', 'label' => 'Administered By'],
            ],
            'rows' => $administrations->map(fn (MedicationAdministration $m) => [
                'when' => ($m->administered_at ?? $m->created_at)->format('Y-m-d H:i'),
                'client' => $this->clientName($m->medication?->serviceUser),
                'medication' => $m->medication?->name ?? '—',
                'status' => str_replace('_', ' ', $m->status),
                'administered_by' => $m->administeredBy->name ?? '—',
            ]),
        ];
    }

    // ---- Clinical / Observations ------------------------------------------

    private function observationTrend(array $filters, array $types, string $title): array
    {
        $observations = Observation::whereIn('type', $types)
            ->whereBetween('recorded_at', ["{$filters['from']} 00:00:00", "{$filters['to']} 23:59:59"])
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
                ['key' => 'recorded_by', 'label' => 'Recorded By'],
            ],
            'rows' => $observations->map(fn (Observation $o) => [
                'when' => $o->recorded_at->format('Y-m-d H:i'),
                'client' => $this->clientName($o->serviceUser),
                'type' => str_replace('_', ' ', $o->type),
                'value' => $this->formatObservationValue($o),
                'recorded_by' => $o->recordedBy->name ?? '—',
            ]),
        ];
    }

    private function formatObservationValue(Observation $observation): string
    {
        $value = $observation->value ?? [];

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
        $alerts = ClinicalAlert::whereBetween('created_at', ["{$filters['from']} 00:00:00", "{$filters['to']} 23:59:59"])
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
            Incident::whereBetween('created_at', ["{$filters['from']} 00:00:00", "{$filters['to']} 23:59:59"])
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
        $cases = SafeguardingCase::whereBetween('created_at', ["{$filters['from']} 00:00:00", "{$filters['to']} 23:59:59"])
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
            Incident::whereBetween('created_at', ["{$filters['from']} 00:00:00", "{$filters['to']} 23:59:59"]),
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
            Incident::whereBetween('created_at', ["{$filters['from']} 00:00:00", "{$filters['to']} 23:59:59"])
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
            Incident::whereBetween('created_at', ["{$filters['from']} 00:00:00", "{$filters['to']} 23:59:59"])
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
            Incident::whereBetween('created_at', ["{$filters['from']} 00:00:00", "{$filters['to']} 23:59:59"])
                ->where('status', '!=', 'closed'),
            $filters['branch_id']
        )->with('serviceUser')->get()->map(fn (Incident $i) => [
            'date' => $i->created_at->format('Y-m-d'),
            'source' => 'Incident',
            'client' => $i->serviceUser ? $this->clientName($i->serviceUser) : '—',
            'description' => str_replace('_', ' ', $i->type),
            'status' => str_replace('_', ' ', $i->status),
        ]);

        $openSafeguarding = SafeguardingCase::whereBetween('created_at', ["{$filters['from']} 00:00:00", "{$filters['to']} 23:59:59"])
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
        $warningDays = 30;
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
        $sections = CarePlanSection::whereNotNull('review_date')
            ->whereDate('review_date', '<', now())
            ->whereHas('carePlan', fn ($q) => $q->where('status', 'active'))
            ->when($filters['branch_id'], fn ($q) => $q->whereHas('carePlan.serviceUser', fn ($su) => $su->where('branch_id', $filters['branch_id'])))
            ->with('carePlan.serviceUser')
            ->orderBy('review_date')
            ->get();

        return [
            'title' => 'Care Plan Reviews Overdue',
            'columns' => [
                ['key' => 'client', 'label' => 'Client'],
                ['key' => 'area', 'label' => 'Care Area'],
                ['key' => 'review_date', 'label' => 'Review Was Due'],
            ],
            'rows' => $sections->map(fn (CarePlanSection $s) => [
                'client' => $this->clientName($s->carePlan?->serviceUser),
                'area' => str_replace('_', ' ', $s->area),
                'review_date' => $s->review_date->toDateString(),
            ]),
        ];
    }

    // ---- GPS / Visit Verification ----------------------------------------

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

            $incidents = Incident::whereBetween('created_at', ["{$filters['from']} 00:00:00", "{$filters['to']} 23:59:59"])
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
